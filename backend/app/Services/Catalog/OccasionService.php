<?php

namespace App\Services\Catalog;

use App\Enums\OccasionStatus;
use App\Models\Occasion;
use App\Models\Tenant;
use App\Services\BaseService;
use Illuminate\Support\Str;

class OccasionService extends BaseService
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(Tenant $tenant, array $data): Occasion
    {
        return Occasion::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'slug' => $this->generateUniqueSlug($tenant, $data['name']),
            'icon' => $data['icon'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            // See CategoryService::create()'s equivalent comment — create()
            // never re-hydrates the migration's column default.
            'status' => OccasionStatus::Active->value,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Occasion $occasion, array $data): Occasion
    {
        if (array_key_exists('name', $data) && $data['name'] !== $occasion->name) {
            $data['slug'] = $this->generateUniqueSlug($occasion->tenant, $data['name'], $occasion->id);
        }

        $occasion->fill($data)->save();

        return $occasion->refresh();
    }

    public function delete(Occasion $occasion): void
    {
        // Deleting an occasion just detaches it from every product it was
        // applied to (product_occasion has no restrictOnDelete — a pure
        // BelongsToMany pivot cascades) — unlike a category, no product
        // becomes structurally invalid, since occasions.* is a many-to-many
        // tag, not a required foreign key on products.
        $occasion->delete();
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
        $query = Occasion::query()->where('tenant_id', $tenant->id)->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->withTrashed()->exists();
    }
}
