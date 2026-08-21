<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductImageType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Multipart upload of one or more gallery images for a product, optionally
 * scoped to a specific variant. Raw file processing (resize, WebP
 * conversion, background removal) is Phase 5.C's job — this endpoint
 * stores the original upload as-is on the tenant disk, matching how
 * StoreController::branding stores a logo before any processing pipeline
 * existed for it either.
 */
class UploadProductImagesRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:20'],
            'images.*' => ['required', 'image', 'max:8192'],
            'variant_id' => [
                'nullable', 'integer',
                Rule::exists('product_variants', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'type' => ['sometimes', Rule::enum(ProductImageType::class)],
        ];
    }
}
