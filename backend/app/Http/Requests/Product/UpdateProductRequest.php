<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing a product, including its variant set. Each entry in `variants`
 * carrying an `id` is matched against the product's existing variants;
 * entries with no `id` are new; existing variants absent from the payload
 * entirely are removed — the "add/update/remove diffing" PROGRESS.md asks
 * for, implemented in ProductService::update() since it requires comparing
 * the payload against what's already in the database, not something a
 * static rule set can express.
 */
class UpdateProductRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['sometimes', 'integer', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'sku' => ['sometimes', 'string', 'max:64'],
            'description' => ['sometimes', 'nullable', 'string'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:255'],
            'base_price' => ['sometimes', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
            'season' => ['sometimes', 'nullable', 'string', 'max:100'],
            'publish_at' => ['sometimes', 'nullable', 'date'],
            'unpublish_at' => ['sometimes', 'nullable', 'date', 'after:publish_at'],
            'meta' => ['sometimes', 'nullable', 'array'],

            'variants' => ['sometimes', 'array', 'min:1'],
            'variants.*.id' => [
                'sometimes', 'integer', Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId),
            ],
            'variants.*.sku' => ['sometimes', 'string', 'max:64'],
            'variants.*.color_attr_id' => [
                'sometimes', 'nullable', 'integer', Rule::exists('attribute_values', 'id')->where('tenant_id', $tenantId),
            ],
            'variants.*.size_attr_id' => [
                'sometimes', 'nullable', 'integer', Rule::exists('attribute_values', 'id')->where('tenant_id', $tenantId),
            ],
            'variants.*.price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['sometimes', 'integer', 'min:0'],
            'variants.*.barcode' => ['sometimes', 'nullable', 'string', 'max:64'],
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
