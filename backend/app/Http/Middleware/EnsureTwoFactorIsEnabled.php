<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mandatory 2FA for tenant owners (PROGRESS.md Phase 2.B). Applied to
 * business routes that shouldn't be reachable until an owner has 2FA
 * turned on — never to /auth/me, /auth/logout, or the /auth/2fa/* setup
 * endpoints themselves, or an owner without 2FA yet would have no way to
 * ever complete setup.
 *
 * Only tenant owners are gated, not every staff account — 2FA for
 * Manager/Staff roles is a per-tenant policy choice that belongs to
 * Phase 2.C's role system, not a platform-wide mandate.
 *
 * Super admins are NOT yet gated by an equivalent check: Mission Control
 * has no self-service 2FA setup flow of its own yet (only tenant users got
 * one in this phase), so enforcing it now would permanently lock every
 * super admin out with no way to comply. That lands once Mission Control
 * grows its own /2fa/* endpoints.
 */
class EnsureTwoFactorIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isTenantOwner() && !$user->hasTwoFactorEnabled()) {
            return ApiResponse::error(
                'Two-factor authentication is required for shop owners. Please finish setup before continuing.',
                Response::HTTP_FORBIDDEN,
                errorCode: 'two_factor_setup_required',
            );
        }

        return $next($request);
    }
}
