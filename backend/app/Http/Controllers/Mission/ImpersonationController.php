<?php

namespace App\Http\Controllers\Mission;

use App\Enums\SuperAdminPermission;
use App\Http\Requests\Mission\InitiateImpersonationRequest;
use App\Models\User;
use App\Services\Mission\ImpersonationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/mission/impersonate/{user} — issues a 30-minute Sanctum
 * token scoped to a single tenant User, so support/Super Admin roles can
 * reproduce a tenant's exact view of the dashboard. $user is resolved via
 * User::withoutTenantScope() deliberately: Mission Control has no tenant
 * context of its own to satisfy TenantScope's fail-closed default, and
 * "any tenant's user, looked up by id, for a permission-gated support
 * action" is exactly the kind of narrow, audited exception Decision D-13
 * describes — this is that decision's fourth bypass, alongside the three
 * already listed there.
 */
class ImpersonationController extends BaseMissionController
{
    public function __construct(private readonly ImpersonationService $impersonations) {}

    public function store(InitiateImpersonationRequest $request, int $user): JsonResponse
    {
        $superAdmin = $this->superAdmin($request);

        if (!$superAdmin->hasPermission(SuperAdminPermission::Tenants)) {
            return $this->error(
                trans('common.unauthorized'),
                Response::HTTP_FORBIDDEN,
                errorCode: 'unauthorized',
            );
        }

        $target = User::withoutTenantScope()->findOrFail($user);

        $result = $this->impersonations->start(
            $superAdmin,
            $target,
            $request->validated('reason'),
            $request->ip(),
            $request->userAgent(),
        );

        return $this->success([
            'token' => $result['token'],
            'expires_at' => $result['impersonation']->expires_at->toIso8601String(),
            'user' => [
                'id' => $target->id,
                'name' => $target->name,
                'email' => $target->email,
            ],
            'tenant_id' => $target->tenant_id,
        ], 'Impersonation session started.');
    }
}
