<?php

namespace App\Http\Requests\Tenant;

use App\Http\Requests\BaseFormRequest;

/**
 * Requesting a custom domain. The hostname is normalised and fully
 * validated in CustomDomainService — including stripping a pasted scheme
 * or path, which is why `url`/`active_url` rules are not used here: a
 * tenant typing "shop.example.com" would fail `url`, and one pasting
 * "https://shop.example.com/" would pass it but store the wrong value.
 */
class CustomDomainRequestRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'min:4', 'max:253'],
        ];
    }
}
