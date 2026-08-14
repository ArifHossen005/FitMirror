<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/auth/me — the authenticated user, their tenant, plan, and
 * limits. `plan`/`limits` are honestly `null`/empty today: `plans` and
 * `plan_limits` don't exist until Phase 3.A, and this endpoint returns
 * what's real right now rather than fabricating placeholder values ahead
 * of that table existing. `permissions` is likewise `[]` until Phase 2.C
 * seeds real roles — every user is implicitly a full-access owner until
 * then, since RBAC doesn't exist yet to say otherwise.
 */
class MeController extends BaseApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->tenant;

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'locale' => $user->locale,
                'status' => $user->status->value,
                'email_verified' => $user->hasVerifiedEmail(),
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
                'is_tenant_owner' => $user->isTenantOwner(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status->value,
            ] : null,
            'plan' => null,
            'limits' => [],
            'permissions' => [],
        ]);
    }
}
