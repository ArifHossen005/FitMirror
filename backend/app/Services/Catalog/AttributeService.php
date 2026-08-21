<?php

namespace App\Services\Catalog;

use App\Enums\AttributeStatus;
use App\Models\Attribute;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Services\BaseService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Attribute (Color/Size/Fabric/Custom) CRUD. No plan limit applies —
 * PlanSeeder defines `categories`/`skus`/`branches`/`staff_accounts`/
 * `storage_gb` and nothing for attributes, matching the product document's
 * own silence on capping how many attribute *types* a tenant may define
 * (only how many products/categories/branches).
 */
class AttributeService extends BaseService
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(Tenant $tenant, array $data): Attribute
    {
        return Attribute::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'slug' => $this->generateUniqueSlug($tenant, $data['name']),
            'type' => $data['type'],
            'sort_order' => $data['sort_order'] ?? 0,
            // See CategoryService::create()'s equivalent comment — create()
            // never re-hydrates the migration's column default.
            'status' => AttributeStatus::Active->value,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Attribute $attribute, array $data): Attribute
    {
        if (array_key_exists('name', $data) && $data['name'] !== $attribute->name) {
            $data['slug'] = $this->generateUniqueSlug($attribute->tenant, $data['name'], $attribute->id);
        }

        $attribute->fill($data)->save();

        return $attribute->refresh();
    }

    /**
     * Deleting an attribute cascades to its values (attribute_values.
     * attribute_id is cascadeOnDelete), but a value already selected by a
     * live variant must not vanish out from under it — variants only
     * nullOnDelete when their specific *value* is removed, so this checks
     * one level up: any value at all in use blocks the whole attribute.
     */
    public function delete(Attribute $attribute): void
    {
        $valueIds = $attribute->values()->pluck('id');

        if ($valueIds->isNotEmpty()) {
            $inUse = ProductVariant::query()
                ->whereIn('color_attr_id', $valueIds)
                ->orWhereIn('size_attr_id', $valueIds)
                ->exists();

            if ($inUse) {
                throw ValidationException::withMessages([
                    'attribute' => ['This attribute has values in use by existing product variants and cannot be deleted.'],
                ]);
            }
        }

        $attribute->delete();
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
        $query = Attribute::query()->where('tenant_id', $tenant->id)->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->withTrashed()->exists();
    }
}
