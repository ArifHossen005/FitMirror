<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Services\Mission\ImpersonationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/auth/impersonation/exit — revokes the *current* token and
 * closes the matching Impersonation audit row. Only callable with an
 * impersonation token itself (ability check below); calling it with an
 * ordinary session token is a no-op 403, not an accidental logout.
 *
 * "Restoring the original session" (PROGRESS.md Phase 2.C) is a frontend
 * responsibility: the dashboard keeps the super admin's own Mission
 * Control token stashed for the duration of the impersonation (see
 * apps/dashboard's ImpersonationBanner, Phase 2.D) and swaps back to it
 * once this endpoint confirms the impersonation token is revoked — the
 * backend's job here is only to make sure that token can never be used
 * again.
 */
class ImpersonationExitController extends BaseApiController
{
    public function __invoke(Request $request, ImpersonationService $impersonations): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        // Checking the token's *name*, not $token->can('impersonation') —
        // an ordinary login token is issued with the wildcard ability
        // ['*'] (see LoginService::issueToken()), and Sanctum's ability
        // check treats '*' as "can everything", which would make an
        // ability-only check here a no-op for every normal session.
        if ($token->name !== 'impersonation') {
            return $this->error(
                'This session is not an impersonation session.',
                Response::HTTP_FORBIDDEN,
                errorCode: 'not_impersonating',
            );
        }

        $impersonations->end($request->user(), $token->id);

        return $this->noContent();
    }
}
