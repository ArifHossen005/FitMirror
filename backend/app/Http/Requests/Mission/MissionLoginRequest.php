<?php

namespace App\Http\Requests\Mission;

use App\Http\Requests\BaseFormRequest;

class MissionLoginRequest extends BaseFormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
