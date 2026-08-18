<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Services\Plan\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/auth/me — the authenticated user, their tenant, plan,
 * limits, features, roles, and permissions. `plan`/`limits`/`features`
 * reflect the tenant's resolved plan (Phase 3.A — PlanService::resolve()
 * falls back to Free for a tenant that hasn't been through checkout yet,
 * so this is never null in practice). `features` is keyed by feature_key
 * (Phase 3.D addition, e.g. `{"campaign_manager": {"enabled": true, "tier":
 * "basic"}}`) — the same data App\Services\Plan\FeatureGate checks
 * server-side, exposed here so the dashboard can render a feature-locked
 * overlay without a dedicated endpoint. `roles`/`permissions` reflect
 * spatie/laravel-permission (Phase 2.C) — empty for a user with no role
 * assigned yet (should not normally happen post-2.C: registration assigns
 * 'owner', invitation acceptance assigns the invited role).
 */
class MeController extends BaseApiController
{
    public function __invoke(Request $request, PlanService $plans): JsonResponse
    {
        $user = $request->user();
        $tenant = $user->tenant;
        $plan = $tenant ? $plans->resolve($tenant) : null;

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
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
            ] : null,
            'limits' => $plan ? $plan->limits->mapWithKeys(fn ($limit) => [$limit->key => $limit->value])->all() : [],
            'features' => $plan
                ? $plan->features->mapWithKeys(fn ($feature) => [
                    $feature->feature_key => ['enabled' => $feature->enabled, 'tier' => $feature->tier()],
                ])->all()
                : [],
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }
}
