<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Active sessions" means Sanctum personal access tokens here — this is an
 * API-only app with no server-side session store to list (SESSION_DRIVER
 * is only used for the tenant-subdomain-cookie-free API surface's CSRF-
 * exempt stateless requests, never for auth state itself).
 */
class ActiveSessionController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()->id;

        $sessions = $request->user()->tokens()->orderByDesc('last_used_at')->get()->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'is_current' => $token->id === $currentTokenId,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
        ]);

        return $this->success($sessions);
    }

    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if ($deleted === 0) {
            return $this->error('Session not found.', Response::HTTP_NOT_FOUND, errorCode: 'session_not_found');
        }

        return $this->noContent();
    }
}
