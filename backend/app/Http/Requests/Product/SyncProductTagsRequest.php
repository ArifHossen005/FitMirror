<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * The product tagging API PROGRESS.md's 5.A checklist calls for — always
 * carries the full desired tag set, diffed via MorphToMany::sync() in
 * ProductService::syncTags(), same shape as SyncProductSizeChartsRequest.
 */
class SyncProductTagsRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tag_ids' => ['present', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('tags', 'id')->where('tenant_id', $this->user()->tenant_id)],
        ];
    }
}
