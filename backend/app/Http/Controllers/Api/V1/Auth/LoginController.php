<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\LoginService;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/auth/login. Two possible success shapes — the client
 * branches on `data.status`:
 *   - "authenticated": data.token is a real, usable Sanctum token.
 *   - "two_factor_required": data.two_factor_token must be submitted with
 *     a TOTP code to POST /api/v1/auth/2fa/challenge before any token is
 *     issued (TwoFactorChallengeController).
 */
class LoginController extends BaseApiController
{
    public function __construct(private readonly LoginService $loginService) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $result = $this->loginService->attempt(
            $request->validated('email'),
            $request->validated('password'),
            $request->ip(),
            $request->userAgent(),
        );

        if ($result['status'] === 'two_factor_required') {
            return $this->success([
                'status' => 'two_factor_required',
                'two_factor_token' => $result['two_factor_token'],
            ]);
        }

        return $this->success([
            'status' => 'authenticated',
            'token' => $result['token'],
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'tenant_id' => $result['user']->tenant_id,
            ],
        ]);
    }
}
