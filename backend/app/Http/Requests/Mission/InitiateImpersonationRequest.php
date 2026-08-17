<?php

namespace App\Http\Requests\Mission;

use App\Http\Requests\BaseFormRequest;

class InitiateImpersonationRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
