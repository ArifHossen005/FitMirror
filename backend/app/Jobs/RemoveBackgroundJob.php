<?php

namespace App\Jobs;

use App\Enums\BackgroundRemovalStatus;
use App\Enums\ProductImageType;
use App\Models\ProductImage;
use App\Services\Media\BackgroundRemovalService;
use App\Services\Media\StorageQuotaService;
use App\Support\TenantStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Sends one `gallery`-type product image through the configured
 * background-removal provider and, on success, stores the transparent PNG
 * as a new `tryon`-type ProductImage row — PROGRESS.md's 5.C "produces
 * AR-ready transparent PNG" + "mark is_tryon_ready on success" checklist
 * items in one job. Dispatched by ProductImageController's manual trigger
 * (there is no automatic dispatch on every gallery upload — see that
 * controller's own docblock for why background removal is opt-in per
 * image, not automatic for every photo).
 *
 * Retries three times with backoff before giving up — a transient
 * provider timeout should not permanently strand a product with no
 * try-on-ready asset. failed() is what PROGRESS.md's "retry + failure
 * notification path" checklist item refers to for the notification half.
 */
class RemoveBackgroundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(private readonly int $sourceImageId)
    {
        $this->onQueue('media');
    }

    public function handle(BackgroundRemovalService $removal, StorageQuotaService $quota): void
    {
        $source = ProductImage::withoutTenantScope()->find($this->sourceImageId);

        if ($source === null) {
            return;
        }

        $source->forceFill([
            'background_removal_status' => BackgroundRemovalStatus::Processing->value,
            'background_removal_attempts' => $this->attempts(),
        ])->save();

        $originalBytes = Storage::disk($source->disk)->get($source->path);

        if ($originalBytes === null) {
            throw new RuntimeException("Source image file is missing from storage: {$source->path}");
        }

        $processedBytes = $removal->removeBackground($originalBytes, basename($source->path));

        $directory = TenantStorage::path($source->tenant_id, 'products/' . $source->product_id);
        $path = $directory . '/tryon-' . Str::random(16) . '.png';
        Storage::disk($source->disk)->put($path, $processedBytes);
        $sizeBytes = strlen($processedBytes);

        ProductImage::query()->create([
            'tenant_id' => $source->tenant_id,
            'product_id' => $source->product_id,
            'variant_id' => $source->variant_id,
            'disk' => $source->disk,
            'path' => $path,
            'type' => ProductImageType::Tryon->value,
            'sort_order' => 0,
            'is_primary' => false,
            'size_bytes' => $sizeBytes,
        ]);

        $quota->increment($source->tenant, $sizeBytes);

        $source->product()->update(['is_tryon_ready' => true]);

        $source->forceFill([
            'background_removal_status' => BackgroundRemovalStatus::Completed->value,
            'background_removal_error' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $source = ProductImage::withoutTenantScope()->find($this->sourceImageId);

        if ($source === null) {
            return;
        }

        $source->forceFill([
            'background_removal_status' => BackgroundRemovalStatus::Failed->value,
            'background_removal_error' => substr($exception->getMessage(), 0, 255),
        ])->save();

        NotifyBackgroundRemovalFailedJob::dispatch($source->id);
    }
}
