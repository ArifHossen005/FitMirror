<?php

namespace App\Services\Media;

use App\Models\ProductImage;
use App\Models\Tenant;
use App\Support\TenantStorage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * The other half of "Build image delete API with S3 cleanup and orphan
 * sweeper job" (PROGRESS.md 5.C) — ProductImageService::delete() already
 * does the S3 cleanup for a row the API knows about; this catches files
 * that exist on disk with **no** `product_images` row pointing at them at
 * all (an upload whose DB write failed after the file was already stored,
 * a row deleted directly in a database console, a derivative left behind
 * by a since-reverted code path). Scoped to one tenant's own
 * `tenants/{id}/products/` prefix at a time — never a bucket-wide listing,
 * which would be both slow and a cross-tenant read.
 */
class OrphanedMediaSweeper
{
    /**
     * @return int number of files deleted
     */
    public function sweep(Tenant $tenant): int
    {
        $prefix = TenantStorage::path($tenant, 'products');
        $disk = Storage::disk('tenant');

        if (!$disk->exists($prefix)) {
            return 0;
        }

        $filesOnDisk = collect($disk->allFiles($prefix));
        $referenced = $this->referencedPaths($tenant);

        $orphans = $filesOnDisk->reject(fn (string $path) => $referenced->contains($path));

        foreach ($orphans as $path) {
            $disk->delete($path);
        }

        return $orphans->count();
    }

    /**
     * @return Collection<int, string>
     */
    private function referencedPaths(Tenant $tenant): Collection
    {
        $rows = ProductImage::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->get(['path', 'derivatives']);

        $paths = collect();

        foreach ($rows as $row) {
            $paths->push($row->path);

            foreach ($row->derivatives ?? [] as $derivativePath) {
                $paths->push($derivativePath);
            }
        }

        return $paths;
    }
}
