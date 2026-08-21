<?php

namespace App\Services\Catalog;

use App\Models\Tag;
use App\Models\Tenant;
use App\Services\BaseService;
use Illuminate\Support\Str;

class TagService extends BaseService
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(Tenant $tenant, array $data): Tag
    {
        return Tag::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'slug' => $this->generateUniqueSlug($tenant, $data['name']),
            'color' => $data['color'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Tag $tag, array $data): Tag
    {
        if (array_key_exists('name', $data) && $data['name'] !== $tag->name) {
            $data['slug'] = $this->generateUniqueSlug($tag->tenant, $data['name'], $tag->id);
        }

        $tag->fill($data)->save();

        return $tag->refresh();
    }

    public function delete(Tag $tag): void
    {
        // taggables rows cascade with the tag (tag_id is cascadeOnDelete),
        // so every product loses this tag automatically — no product
        // becomes invalid, same reasoning as OccasionService::delete().
        $tag->delete();
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
        $query = Tag::query()->where('tenant_id', $tenant->id)->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->withTrashed()->exists();
    }
}
