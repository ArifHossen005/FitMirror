<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Catalog\ReorderCategoriesRequest;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\Catalog\CategoryService;
use App\Services\Plan\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Category tree management. Unlike StoreController's paginated list,
 * index() always returns the tenant's complete tree, nested — a
 * drag-and-drop tree editor (Phase 5.F) needs the whole structure at once,
 * and Category::MAX_DEPTH bounds it small enough that pagination would
 * only get in the way.
 */
class CategoryController extends BaseApiController
{
    public function __construct(
        private readonly CategoryService $categories,
        private readonly PlanService $plans,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $tenant = $request->user()->tenant;

        $all = Category::query()
            ->where('tenant_id', $tenant->id)
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        return $this->success([
            'categories' => $this->nest($all, null),
            'category_limit' => [
                'used' => $this->categories->countTowardLimit($tenant),
                'max' => $this->plans->limit($tenant, 'categories'),
            ],
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = $this->categories->create($request->user()->tenant, $request->validated());

        return $this->created($this->present($category), 'Category created successfully.');
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        return $this->success([
            ...$this->present($category->loadCount('products')),
            'ancestors' => $this->categories->ancestors($category)->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
            ])->values(),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $updated = $this->categories->update($category, $request->validated());

        return $this->success($this->present($updated), 'Category updated successfully.');
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $this->categories->delete($category);

        return $this->noContent();
    }

    public function reorder(ReorderCategoriesRequest $request): JsonResponse
    {
        $this->authorize('reorder', Category::class);

        $this->categories->reorder($request->user()->tenant, $request->validated()['order']);

        return $this->success(null, 'Category order updated successfully.');
    }

    /**
     * @param Collection<int, Category> $all
     * @return list<array<string, mixed>>
     */
    private function nest(Collection $all, ?int $parentId): array
    {
        return $all->where('parent_id', $parentId)
            ->map(fn (Category $category) => [
                ...$this->present($category),
                'product_count' => $category->products_count ?? 0,
                'children' => $this->nest($all, $category->id),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Category $category): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'icon' => $category->icon,
            'image_url' => $category->imageUrl(),
            'gender' => $category->gender?->value,
            'sort_order' => $category->sort_order,
            'status' => $category->status->value,
            'status_label' => $category->status->label(),
            'created_at' => $category->created_at?->toIso8601String(),
        ];
    }
}
