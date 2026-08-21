<?php

namespace App\Http\Requests\Store;

use App\Http\Requests\BaseFormRequest;

/**
 * Creating a franchise group.
 *
 * `member_tenant_ids` deliberately carries no `exists:tenants,id` rule:
 * that rule would confirm to any caller whether an arbitrary tenant id is
 * real, which is a cross-tenant existence oracle. FranchiseService::
 * addMember() resolves each id and reports a single generic message for
 * one that does not resolve.
 */
class StoreFranchiseGroupRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'member_tenant_ids' => ['sometimes', 'array', 'max:500'],
            'member_tenant_ids.*' => ['integer', 'min:1'],
        ];
    }
}
