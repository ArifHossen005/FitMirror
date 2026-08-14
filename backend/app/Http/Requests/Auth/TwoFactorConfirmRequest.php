<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class TwoFactorConfirmRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }
}
