<?php

namespace App\Http\Controllers\Mission;

use App\Http\Requests\Mission\MissionLoginRequest;
use App\Models\SuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mission Control's own login/logout — deliberately not reusing the tenant
 * auth flow (Phase 2.B `Api\V1\AuthController`, once it exists). A super
 * admin authenticates against the `super_admins` table only; this
 * controller never touches App\Models\User.
 */
class MissionAuthController extends BaseMissionController
{
    public function login(MissionLoginRequest $request): JsonResponse
    {
        $superAdmin = SuperAdmin::query()
            ->where('email', $request->validated('email'))
            ->first();

        if (!$superAdmin || !Hash::check($request->validated('password'), $superAdmin->password)) {
            return $this->error(
                trans('common.auth.invalid_credentials'),
                Response::HTTP_UNAUTHORIZED,
                errorCode: 'invalid_credentials',
            );
        }

        if (!$superAdmin->isActive()) {
            return $this->error(
                trans('common.mission.suspended'),
                Response::HTTP_FORBIDDEN,
                errorCode: 'super_admin_suspended',
            );
        }

        $superAdmin->forceFill(['last_login_at' => now()])->save();

        $token = $superAdmin->createToken('mission-control')->plainTextToken;

        return $this->success([
            'token' => $token,
            'super_admin' => [
                'id' => $superAdmin->id,
                'name' => $superAdmin->name,
                'email' => $superAdmin->email,
                'role' => $superAdmin->role->value,
                'status' => $superAdmin->status->value,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->superAdmin($request)->currentAccessToken()->delete();

        return $this->noContent();
    }
}
