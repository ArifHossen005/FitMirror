<?php

namespace App\Http\Middleware;

use App\Services\Plan\FeatureGate;
use App\Support\ApiResponse;
use App\Support\PlanGateResponse;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware form of FeatureGate — `->middleware('plan.feature:
 * campaign_manager')`. Put after `tenant.active` in the stack (needs a
 * resolved tenant). No route uses this yet — Phase 3.A builds the
 * mechanism ahead of Phase 7+'s campaign/loyalty/etc. routes that will
 * actually attach it, the same "build the primitive now, wire it up when
 * the real route lands" pattern as `tenant.active`/`tenant.2fa` in
 * Phase 2.B.
 */
class EnforcePlanFeature
{
    public function __construct(private readonly FeatureGate $featureGate) {}

    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $tenant = app(TenantContext::class)->get();

        if (!$tenant) {
            return ApiResponse::error(
                trans('common.not_found', ['item' => 'Tenant']),
                Response::HTTP_NOT_FOUND,
                errorCode: 'tenant_not_found',
            );
        }

        if (!$this->featureGate->allows($tenant, $featureKey)) {
            return PlanGateResponse::featureUnavailable($featureKey);
        }

        return $next($request);
    }
}
