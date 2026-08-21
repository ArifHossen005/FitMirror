<?php

namespace App\Http\Requests\Catalog;

use App\Enums\CategoryGender;
use App\Enums\CategoryStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing a category. Every field is `sometimes`, matching UpdateStoreRequest's
 * PATCH semantics. Re-parenting (`parent_id`) is allowed here but the
 * resulting tree is re-validated in CategoryService::update() — a request
 * can't detect "moving category A under its own descendant B" without
 * loading the tree, so that check cannot live here.
 */
class UpdateCategoryRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', Rule::enum(CategoryGender::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(CategoryStatus::class)],
        ];
    }
}
