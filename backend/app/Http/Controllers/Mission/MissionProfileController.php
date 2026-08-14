<?php

namespace App\Http\Controllers\Mission;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/mission/me — the Mission Control analog of the tenant API's
 * `GET /api/v1/user` (routes/api_v1.php). Exists mainly to give the
 * `auth:super_admin` guard and `super_admin` (EnsureSuperAdmin) middleware
 * a real protected route to prove out end-to-end, ahead of the full
 * login/2FA flow landing in a later phase.
 */
class MissionProfileController extends BaseMissionController
{
    public function __invoke(Request $request): JsonResponse
    {
        $superAdmin = $this->superAdmin($request);

        return $this->success([
            'id' => $superAdmin->id,
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'role' => $superAdmin->role->value,
            'role_label' => $superAdmin->role->label(),
            'status' => $superAdmin->status->value,
            'permissions' => array_map(
                fn ($permission) => $permission->value,
                $superAdmin->role->permissions(),
            ),
            'two_factor_enabled' => $superAdmin->hasTwoFactorEnabled(),
            'last_login_at' => $superAdmin->last_login_at?->toIso8601String(),
        ]);
    }
}
