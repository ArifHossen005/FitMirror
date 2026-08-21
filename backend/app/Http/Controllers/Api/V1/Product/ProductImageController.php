<?php

namespace App\Http\Controllers\Api\V1\Product;

use App\Enums\ProductImageType;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Product\ReorderProductImagesRequest;
use App\Http\Requests\Product\UploadProductImagesRequest;
use App\Http\Requests\Product\UploadTryonAssetRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Product\ProductImageService;
use Illuminate\Http\JsonResponse;

/**
 * Split out of ProductController the same way KioskDeviceController is
 * split from StoreController — image upload is a distinct multipart
 * concern (PHP only populates uploaded files on a POST body, never a JSON
 * PATCH, same reasoning as StoreBrandingRequest).
 *
 * Background removal is opt-in per gallery image (removeBackground()), not
 * automatically dispatched by store() for every upload — a product often
 * has several lifestyle/detail shots and only one or two need an AR-ready
 * cutout, and the provider is a paid, rate-limited external call
 * (config('background_removal')) that shouldn't fire on photos nobody
 * asked to try on.
 */
class ProductImageController extends BaseApiController
{
    public function __construct(private readonly ProductImageService $images) {}

    public function store(UploadProductImagesRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $data = $request->validated();

        $this->images->upload(
            $product,
            $request->file('images'),
            $data['variant_id'] ?? null,
            isset($data['type']) ? ProductImageType::from($data['type']) : ProductImageType::Gallery,
        );

        return $this->created($this->presentGallery($product), 'Images uploaded successfully.');
    }

    public function reorder(ReorderProductImagesRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $data = $request->validated();
        $this->images->reorder($product, $data['order'], $data['primary_image_id'] ?? null);

        return $this->success($this->presentGallery($product), 'Image order updated successfully.');
    }

    public function destroy(Product $product, ProductImage $image): JsonResponse
    {
        $this->authorize('update', $product);
        abort_if($image->product_id !== $product->id, 404);

        $this->images->delete($image);

        return $this->noContent();
    }

    /**
     * Submits (or resubmits, after a failure) a gallery image for AI
     * background removal.
     */
    public function removeBackground(Product $product, ProductImage $image): JsonResponse
    {
        $this->authorize('update', $product);
        abort_if($image->product_id !== $product->id, 404);

        $this->images->requestBackgroundRemoval($image);

        return $this->success(null, 'Background removal queued.');
    }

    public function uploadTryonAsset(UploadTryonAssetRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $image = $this->images->uploadTryonAsset(
            $product,
            $request->file('image'),
            $request->validated()['variant_id'] ?? null,
        );

        return $this->created([
            'id' => $image->id,
            'variant_id' => $image->variant_id,
            'url' => $image->url(),
        ], 'Try-on asset uploaded successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentGallery(Product $product): array
    {
        return [
            'images' => $product->images()->get()->map(fn (ProductImage $image) => [
                'id' => $image->id,
                'variant_id' => $image->variant_id,
                'url' => $image->url(),
                'type' => $image->type->value,
                'sort_order' => $image->sort_order,
                'is_primary' => $image->is_primary,
                'background_removal_status' => $image->background_removal_status?->value,
                'background_removal_error' => $image->background_removal_error,
            ])->values(),
        ];
    }
}
