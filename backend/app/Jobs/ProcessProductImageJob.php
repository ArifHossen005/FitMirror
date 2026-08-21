<?php

namespace App\Jobs;

use App\Models\ProductImage;
use App\Services\Media\ImageProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Dispatched by ProductImageService::upload() (and ProductService
 * ::duplicate()) for every stored image — generates sm/md/lg WebP
 * derivatives asynchronously so the upload response doesn't wait on image
 * processing. Takes an image *id*, not a ProductImage model, the same
 * GenerateInvoicePdfJob reasoning: a plain `queue:work` worker has no
 * ambient TenantContext for SerializesModels' re-fetch to run against.
 */
class ProcessProductImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(private readonly int $productImageId)
    {
        $this->onQueue('media');
    }

    public function handle(ImageProcessingService $processor): void
    {
        $image = ProductImage::withoutTenantScope()->find($this->productImageId);

        // The image (or its whole product) may have been deleted before
        // this job ran — not an error, just nothing left to process.
        if ($image === null) {
            return;
        }

        $derivatives = $processor->generateDerivatives($image);

        $image->forceFill(['derivatives' => $derivatives])->save();
    }
}
