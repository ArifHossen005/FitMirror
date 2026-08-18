<?php

namespace App\Http\Requests\Subscription;

use App\Http\Requests\BaseFormRequest;

class UpdateAutoRenewRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'auto_renew' => ['required', 'boolean'],
        ];
    }
}
