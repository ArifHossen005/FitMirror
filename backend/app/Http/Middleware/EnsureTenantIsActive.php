<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Support\ApiResponse;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to every route that requires a resolved, usable tenant — put
 * after ResolveTenant in the route middleware stack. Distinguishes "no
 * tenant resolved at all" (404 — wrong subdomain/header, not a business
 * state) from "tenant resolved but not usable" (403, with a message
 * specific to why: suspended, expired, or still pending approval).
 */
class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);

        if (!$context->has()) {
            return ApiResponse::error(
                trans('common.not_found', ['item' => 'Tenant']),
                Response::HTTP_NOT_FOUND,
                errorCode: 'tenant_not_found',
            );
        }

        $tenant = $context->get();

        if (!$tenant->isUsable()) {
            return ApiResponse::error(
                message: $this->messageFor($tenant->status),
                status: Response::HTTP_FORBIDDEN,
                errorCode: 'tenant_' . $tenant->status->value,
            );
        }

        return $next($request);
    }

    private function messageFor(TenantStatus $status): string
    {
        return match ($status) {
            TenantStatus::Suspended => trans('common.tenant.suspended'),
            TenantStatus::Expired => trans('common.tenant.expired'),
            TenantStatus::Pending => trans('common.tenant.pending_approval'),
            TenantStatus::Rejected => trans('common.tenant.pending_approval'),
            default => trans('common.error'),
        };
    }
}
