<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Services\Auth\LoginService;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/auth/2fa/challenge — the second step of login for an
 * account with 2FA enabled. Accepts either a live TOTP `code` or a
 * one-time `recovery_code`.
 */
class TwoFactorChallengeController extends BaseApiController
{
    public function __construct(private readonly LoginService $loginService) {}

    public function __invoke(TwoFactorChallengeRequest $request): JsonResponse
    {
        $result = $this->loginService->completeTwoFactorChallenge(
            $request->validated('two_factor_token'),
            $request->validated('code'),
            $request->validated('recovery_code'),
        );

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
