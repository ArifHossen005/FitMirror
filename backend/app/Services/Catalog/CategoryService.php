<?php

namespace App\Services\Catalog;

use App\Enums\CategoryStatus;
use App\Models\Category;
use App\Models\Tenant;
use App\Services\BaseService;
use App\Services\Plan\PlanService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Category CRUD plus the tree-walking helpers PROGRESS.md's 5.A checklist
 * calls for (ancestors, descendants, depth limit). No nested-set/
 * materialized-path package is installed anywhere in this codebase — see
 * Decision D-24 — so the tree is a plain adjacency list (`parent_id`)
 * walked in PHP. Every tenant's catalog tree is small enough (capped at
 * Category::MAX_DEPTH levels, realistically dozens of rows) that this is
 * cheap; a materialized-path rewrite is a Phase 10+ (Analytics/BI-driven)
 * concern if it ever stops being cheap, not a Phase 5 one.
 *
 * The plan check follows the exact pattern StoreService established for
 * `branches` — see PlanService::assertWithinLimit()'s own docblock.
 */
class CategoryService extends BaseService
{
    public function __construct(private readonly PlanService $plans) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(Tenant $tenant, array $data): Category
    {
        $this->plans->assertWithinLimit($tenant, 'categories', $this->countTowardLimit($tenant));

        $parent = $this->resolveParent($tenant, $data['parent_id'] ?? null);

        if ($parent !== null) {
            $this->assertWithinDepthLimit($this->depth($parent) + 1);
        }

        return $this->transaction(function () use ($tenant, $data, $parent) {
            return Category::query()->create([
                'tenant_id' => $tenant->id,
                'parent_id' => $parent?->id,
                'name' => $data['name'],
                'slug' => $this->generateUniqueSlug($tenant, $data['name']),
                'icon' => $data['icon'] ?? null,
                'gender' => $data['gender'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                // Eloquent's create() does not re-hydrate DB-side column
                // defaults onto the returned instance, so this is set
                // explicitly rather than left to the migration's default —
                // otherwise CategoryController::present() would read a null
                // $category->status immediately after creation.
                'status' => CategoryStatus::Active->value,
            ]);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Category $category, array $data): Category
    {
        if (array_key_exists('parent_id', $data)) {
            $newParent = $this->resolveParent($category->tenant, $data['parent_id']);
            $this->assertNotOwnDescendant($category, $newParent);

            if ($newParent !== null) {
                $this->assertWithinDepthLimit($this->depth($newParent) + 1);
            }

            $data['parent_id'] = $newParent?->id;
        }

        if (array_key_exists('name', $data) && $data['name'] !== $category->name) {
            $data['slug'] = $this->generateUniqueSlug($category->tenant, $data['name'], $category->id);
        }

        return $this->transaction(function () use ($category, $data) {
            $category->fill($data)->save();

            return $category->refresh();
        });
    }

    /**
     * A category with children or products cannot be removed — both would
     * be silently orphaned (children by the FK's restrictOnDelete()
     * backstop, products because Product::category_id is required, not
     * nullable). The tenant must re-parent or reassign first, the same
     * "promote another branch to main" shape as StoreService::delete().
     */
    public function delete(Category $category): void
    {
        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => ['Move or delete this category\'s subcategories before deleting it.'],
            ]);
        }

        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => ['Move this category\'s products to another category before deleting it.'],
            ]);
        }

        $category->delete();
    }

    /**
     * Persists a new sort_order for every row in $order, which must all
     * share one parent (drag-and-drop only ever reorders one sibling
     * group at a time) — enforced here because a form request cannot
     * compare loaded rows against each other.
     *
     * @param list<array{id: int, sort_order: int}> $order
     */
    public function reorder(Tenant $tenant, array $order): void
    {
        $ids = array_column($order, 'id');

        $categories = Category::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($categories->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'order' => ['One or more categories could not be found.'],
            ]);
        }

        $parentIds = $categories->pluck('parent_id')->unique();

        if ($parentIds->count() > 1) {
            throw ValidationException::withMessages([
                'order' => ['All reordered categories must share the same parent.'],
            ]);
        }

        $this->transaction(function () use ($order) {
            foreach ($order as $row) {
                Category::query()->whereKey($row['id'])->update(['sort_order' => $row['sort_order']]);
            }
        });
    }

    /**
     * Categories the plan's `categories` limit counts against — every
     * non-deleted category, active or inactive alike (CategoryStatus::
     * countsTowardLimit() is always true; the method exists for parity
     * with StoreStatus's own predicate and to keep the call site
     * self-documenting).
     */
    public function countTowardLimit(Tenant $tenant): int
    {
        return Category::query()->where('tenant_id', $tenant->id)->count();
    }

    /**
     * Root-first list of every ancestor of $category, not including itself.
     *
     * @return Collection<int, Category>
     */
    public function ancestors(Category $category): Collection
    {
        $chain = [];
        $current = $category->parent;

        while ($current !== null) {
            $chain[] = $current;
            $current = $current->parent;
        }

        return new Collection(array_reverse($chain));
    }

    public function depth(Category $category): int
    {
        return $this->ancestors($category)->count();
    }

    /**
     * Every descendant of $category, breadth-first, flattened. Bounded by
     * Category::MAX_DEPTH so this can never run away on a malformed tree.
     *
     * @return Collection<int, Category>
     */
    public function descendants(Category $category): Collection
    {
        $result = new Collection;
        $frontier = [$category->id];

        for ($level = 0; $level < Category::MAX_DEPTH && $frontier !== []; $level++) {
            $children = Category::query()
                ->where('tenant_id', $category->tenant_id)
                ->whereIn('parent_id', $frontier)
                ->orderBy('sort_order')
                ->get();

            $result = $result->merge($children);
            $frontier = $children->pluck('id')->all();
        }

        return $result;
    }

    private function assertWithinDepthLimit(int $prospectiveDepth): void
    {
        if ($prospectiveDepth >= Category::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'parent_id' => ['Categories cannot be nested more than ' . Category::MAX_DEPTH . ' levels deep.'],
            ]);
        }
    }

    /**
     * A category can never become its own descendant's child — that would
     * disconnect the subtree from the tree entirely (a cycle with no root).
     */
    private function assertNotOwnDescendant(Category $category, ?Category $newParent): void
    {
        if ($newParent === null) {
            return;
        }

        if ($newParent->id === $category->id) {
            throw ValidationException::withMessages([
                'parent_id' => ['A category cannot be its own parent.'],
            ]);
        }

        if ($this->descendants($category)->contains('id', $newParent->id)) {
            throw ValidationException::withMessages([
                'parent_id' => ['A category cannot be moved under one of its own subcategories.'],
            ]);
        }
    }

    private function resolveParent(Tenant $tenant, ?int $parentId): ?Category
    {
        if ($parentId === null) {
            return null;
        }

        return Category::query()->where('tenant_id', $tenant->id)->findOrFail($parentId);
    }

    private function generateUniqueSlug(Tenant $tenant, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($tenant, $slug, $ignoreId)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(Tenant $tenant, string $slug, ?int $ignoreId): bool
    {
        $query = Category::query()->where('tenant_id', $tenant->id)->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->withTrashed()->exists();
    }
}
