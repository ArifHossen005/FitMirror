<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\BaseFormRequest;
use App\Services\Staff\StaffInvitationService;
use Illuminate\Validation\Rule;

class InviteStaffRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(StaffInvitationService::invitableRoles())],
        ];
    }
}
