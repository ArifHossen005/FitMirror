<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Drag-and-drop sort persistence for one sibling group at a time — every id
 * in the payload must already share the same parent, checked in
 * CategoryService::reorder() since a form request cannot compare rows
 * against each other, only against static rules.
 */
class ReorderCategoriesRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*.id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('categories', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
