<?php

namespace App\Http\Requests\Tenant;

use App\Http\Requests\BaseFormRequest;
use App\Services\Store\SubdomainService;

/**
 * Claiming `{subdomain}.fitmirror.com`.
 *
 * Only the coarse shape is checked here. Reserved-word and availability
 * rules live in SubdomainService so the assignment endpoint and the live
 * availability check the dashboard calls as the owner types return
 * identical wording — a `unique` rule here would produce Laravel's generic
 * message for one and the service's specific one for the other.
 */
class AssignSubdomainRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subdomain' => [
                'required',
                'string',
                'min:' . SubdomainService::MIN_LENGTH,
                'max:' . SubdomainService::MAX_LENGTH,
            ],
        ];
    }
}
