<?php

namespace App\Services\Product;

use App\Enums\BackgroundRemovalStatus;
use App\Enums\ProductImageType;
use App\Jobs\ProcessProductImageJob;
use App\Jobs\RemoveBackgroundJob;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\BaseService;
use App\Services\Media\ImageProcessingService;
use App\Services\Media\StorageQuotaService;
use App\Support\TenantStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores the raw gallery uploads PROGRESS.md's 5.B "nested variants and
 * images" bullet asks for, plus the Phase 5.C additions layered on top:
 * the plan's `storage_gb` quota is checked before every upload and kept in
 * sync via StorageQuotaService, and each stored file is handed to
 * ProcessProductImageJob (queue `media`) to generate its sm/md/lg WebP
 * derivatives. The original upload is still stored as-is here — resizing
 * happens asynchronously so the upload response doesn't wait on it.
 *
 * "Virus-safe handling" (5.C's own wording) is two things, not a scanning
 * service this codebase doesn't have: (1) an uploaded file's *content*, not
 * just its extension, is validated as a real decodable image —
 * `UploadProductImagesRequest`'s `image` rule already verifies this via
 * `getimagesize()`, and `ProcessProductImageJob`'s own Intervention decode
 * step would fail loudly on anything that slipped past that; (2) an
 * uploaded file is never executed, included, or served with an
 * attacker-controlled content-type — it is only ever read as binary image
 * data and re-encoded, never passed through unmodified except as the
 * original gallery copy on the `tenant` disk (`public` visibility, no
 * script execution possible there).
 */
class ProductImageService extends BaseService
{
    private const DIRECTORY = 'products';

    public function __construct(private readonly StorageQuotaService $quota) {}

    /**
     * @param list<UploadedFile> $files
     */
    public function upload(Product $product, array $files, ?int $variantId, ProductImageType $type): void
    {
        $incomingBytes = array_sum(array_map(fn (UploadedFile $file) => $file->getSize() ?: 0, $files));
        $this->quota->assertWithinQuota($product->tenant, $incomingBytes);

        $hasPrimary = $product->images()->where('is_primary', true)->exists();
        $created = [];

        $this->transaction(function () use ($product, $files, $variantId, $type, &$hasPrimary, &$created) {
            foreach ($files as $file) {
                $directory = TenantStorage::path($product->tenant_id, self::DIRECTORY . '/' . $product->id);
                $filename = Str::random(24) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs($directory, $filename, 'tenant');
                $sizeBytes = $file->getSize() ?: 0;

                $image = ProductImage::query()->create([
                    'tenant_id' => $product->tenant_id,
                    'product_id' => $product->id,
                    'variant_id' => $variantId,
                    'disk' => 'tenant',
                    'path' => $path,
                    'type' => $type->value,
                    'sort_order' => $product->images()->max('sort_order') + 1,
                    // The very first image a product ever receives becomes
                    // its primary automatically, so a product is never left
                    // with a gallery but no primary photo for the dashboard
                    // list/kiosk card to show.
                    'is_primary' => !$hasPrimary,
                    'size_bytes' => $sizeBytes,
                ]);

                $this->quota->increment($product->tenant, $sizeBytes);
                $hasPrimary = true;
                $created[] = $image;
            }
        });

        foreach ($created as $image) {
            ProcessProductImageJob::dispatch($image->id);
        }
    }

    /**
     * @param list<array{id: int, sort_order: int}> $order
     */
    public function reorder(Product $product, array $order, ?int $primaryImageId): void
    {
        $ids = array_column($order, 'id');
        $owned = $product->images()->whereIn('id', $ids)->count();

        if ($owned !== count($ids)) {
            throw ValidationException::withMessages(['order' => ['One or more images do not belong to this product.']]);
        }

        $this->transaction(function () use ($product, $order, $primaryImageId) {
            foreach ($order as $row) {
                ProductImage::query()->whereKey($row['id'])->update(['sort_order' => $row['sort_order']]);
            }

            if ($primaryImageId !== null) {
                $product->images()->update(['is_primary' => false]);
                ProductImage::query()->whereKey($primaryImageId)->update(['is_primary' => true]);
            }
        });
    }

    public function delete(ProductImage $image): void
    {
        $wasPrimary = $image->is_primary;
        $product = $image->product;

        $this->deleteFiles($image);
        $this->quota->decrement($product->tenant, $image->size_bytes);

        $image->delete();

        // A deleted primary image hands the role to whatever remains, so
        // the dashboard/kiosk never render a product with zero primary
        // images while it still has photos left.
        if ($wasPrimary) {
            $next = $product->images()->orderBy('sort_order')->first();
            $next?->update(['is_primary' => true]);
        }
    }

    /**
     * Deletes the original file and every generated derivative — the
     * counterpart ProductService::delete() calls for every image on a
     * deleted product, and what delete() above calls for one image.
     */
    public function deleteFiles(ProductImage $image): void
    {
        if (Storage::disk($image->disk)->exists($image->path)) {
            Storage::disk($image->disk)->delete($image->path);
        }

        app(ImageProcessingService::class)->deleteDerivatives($image);
    }

    /**
     * Queues $image (must be a `gallery`-type row) for AI background
     * removal. Callable again on a `failed` row — RemoveBackgroundJob
     * always re-reads the current file from storage, so a retry is
     * identical to the first attempt in every way except the attempt
     * counter.
     */
    public function requestBackgroundRemoval(ProductImage $image): void
    {
        if ($image->type !== ProductImageType::Gallery) {
            throw ValidationException::withMessages([
                'image' => ['Only a gallery image can be submitted for background removal.'],
            ]);
        }

        $image->forceFill(['background_removal_status' => BackgroundRemovalStatus::Pending->value])->save();

        RemoveBackgroundJob::dispatch($image->id);
    }

    /**
     * The manual AR-asset re-upload PROGRESS.md's 5.C checklist asks for —
     * an owner-supplied PNG replaces whatever `tryon`-type image already
     * exists for this product/variant scope (there is only ever one
     * *active* try-on asset per variant, per ProductImageType::Tryon's own
     * docblock), rather than accumulating a second one alongside it.
     */
    public function uploadTryonAsset(Product $product, UploadedFile $file, ?int $variantId): ProductImage
    {
        $this->quota->assertWithinQuota($product->tenant, $file->getSize() ?: 0);

        return $this->transaction(function () use ($product, $file, $variantId) {
            $existing = $product->images()
                ->where('type', ProductImageType::Tryon->value)
                ->where('variant_id', $variantId)
                ->first();

            if ($existing !== null) {
                $this->deleteFiles($existing);
                $this->quota->decrement($product->tenant, $existing->size_bytes);
                $existing->delete();
            }

            $directory = TenantStorage::path($product->tenant_id, self::DIRECTORY . '/' . $product->id);
            $filename = 'tryon-' . Str::random(16) . '.png';
            $path = $file->storeAs($directory, $filename, 'tenant');
            $sizeBytes = $file->getSize() ?: 0;

            $image = ProductImage::query()->create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'disk' => 'tenant',
                'path' => $path,
                'type' => ProductImageType::Tryon->value,
                'sort_order' => 0,
                'is_primary' => false,
                'size_bytes' => $sizeBytes,
            ]);

            $this->quota->increment($product->tenant, $sizeBytes);
            $product->update(['is_tryon_ready' => true]);

            return $image;
        });
    }
}
