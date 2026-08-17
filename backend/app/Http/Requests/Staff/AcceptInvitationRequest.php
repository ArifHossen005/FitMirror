<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
