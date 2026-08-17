<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\BaseFormRequest;
use App\Services\Staff\StaffInvitationService;
use Illuminate\Validation\Rule;

class UpdateStaffRoleRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(StaffInvitationService::invitableRoles())],
        ];
    }
}
