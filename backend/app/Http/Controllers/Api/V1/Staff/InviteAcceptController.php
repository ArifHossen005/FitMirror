<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Staff\AcceptInvitationRequest;
use App\Services\Staff\StaffInvitationService;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/staff/invitations/accept — unauthenticated, throttled via
 * the `auth` limiter (same class of endpoint as login/register: no session
 * exists yet, and it's a plausible target for brute-forcing invitation
 * tokens).
 */
class InviteAcceptController extends BaseApiController
{
    public function __invoke(AcceptInvitationRequest $request, StaffInvitationService $invitations): JsonResponse
    {
        $result = $invitations->accept($request->validated('token'), [
            'name' => $request->validated('name'),
            'password' => $request->validated('password'),
        ]);

        return $this->success([
            'token' => $result['token'],
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
            ],
            'tenant' => [
                'id' => $result['tenant']->id,
                'name' => $result['tenant']->name,
                'slug' => $result['tenant']->slug,
            ],
        ], 'Invitation accepted.');
    }
}
