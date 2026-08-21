<?php

namespace App\Http\Requests\Catalog;

use App\Enums\CategoryGender;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating a category. `slug` is deliberately not accepted from the client
 * — CategoryService derives and uniques it from `name`, the same pattern
 * RegistrationService uses for a tenant's own slug. `parent_id` is
 * constrained to the authenticated user's own tenant here (a raw
 * Rule::exists() bypasses TenantScope, unlike model queries, so the tenant
 * filter has to be spelled out explicitly); the depth-limit and no-cycles
 * checks depend on walking the tree and so live in CategoryService instead.
 */
class StoreCategoryRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'icon' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::enum(CategoryGender::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
