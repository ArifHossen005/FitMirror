<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating a product with its variants in one call, per PROGRESS.md's own
 * "nested variants ... wrapped in a transaction" wording — ProductService
 * ::create() does the actual transaction. Images are deliberately a
 * separate multipart endpoint (ProductController::uploadImages), not part
 * of this JSON body — PHP does not populate uploaded files into a JSON
 * request body, the same reason StoreBrandingRequest is split from
 * StoreStoreRequest.
 *
 * Every FK id (`category_id`, `variants.*.color_attr_id`, `occasion_ids`,
 * etc.) is constrained to the authenticated user's own tenant here, since a
 * raw Rule::exists() bypasses TenantScope. Cross-field checks that need the
 * *parent* of a referenced row loaded (e.g. "is this attribute value
 * actually a Color") live in ProductService, which already loads it.
 */
class StoreProductRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'sku' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:255'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:base_price'],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
            'season' => ['nullable', 'string', 'max:100'],
            'publish_at' => ['nullable', 'date'],
            'unpublish_at' => ['nullable', 'date', 'after:publish_at'],
            'meta' => ['nullable', 'array'],

            'variants' => ['required', 'array', 'min:1'],
            'variants.*.sku' => ['required', 'string', 'max:64', 'distinct'],
            'variants.*.color_attr_id' => [
                'nullable', 'integer', Rule::exists('attribute_values', 'id')->where('tenant_id', $tenantId),
            ],
            'variants.*.size_attr_id' => [
                'nullable', 'integer', Rule::exists('attribute_values', 'id')->where('tenant_id', $tenantId),
            ],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['sometimes', 'integer', 'min:0'],
            'variants.*.barcode' => ['nullable', 'string', 'max:64'],
            'variants.*.status' => ['sometimes', Rule::enum(ProductVariantStatus::class)],

            'occasion_ids' => ['sometimes', 'array'],
            'occasion_ids.*' => ['integer', Rule::exists('occasions', 'id')->where('tenant_id', $tenantId)],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('tags', 'id')->where('tenant_id', $tenantId)],
            'attribute_value_ids' => ['sometimes', 'array'],
            'attribute_value_ids.*' => [
                'integer', Rule::exists('attribute_values', 'id')->where('tenant_id', $tenantId),
            ],
            'size_chart_ids' => ['sometimes', 'array'],
            'size_chart_ids.*' => ['integer', Rule::exists('size_charts', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
