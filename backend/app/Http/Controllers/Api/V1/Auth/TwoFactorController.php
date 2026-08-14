<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Auth\TwoFactorConfirmRequest;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TOTP 2FA management for the authenticated tenant user — enable/confirm/
 * disable and recovery-code regeneration. The login-time challenge itself
 * (submitting a code to actually get a token) is TwoFactorChallengeController.
 */
class TwoFactorController extends BaseApiController
{
    public function __construct(private readonly TwoFactorService $twoFactorService) {}

    public function enable(Request $request): JsonResponse
    {
        $setup = $this->twoFactorService->startSetup($request->user());

        return $this->success($setup, message: 'Scan the QR code in your authenticator app, then confirm with a code.');
    }

    public function confirm(TwoFactorConfirmRequest $request): JsonResponse
    {
        $recoveryCodes = $this->twoFactorService->confirm($request->user(), $request->validated('code'));

        if ($recoveryCodes === null) {
            return $this->error(
                'That code is invalid or expired.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                errorCode: 'invalid_two_factor_code',
            );
        }

        return $this->success(
            ['recovery_codes' => $recoveryCodes],
            message: 'Two-factor authentication enabled. Save these recovery codes somewhere safe — they will not be shown again.',
        );
    }

    public function disable(Request $request): JsonResponse
    {
        $this->twoFactorService->disable($request->user());

        return $this->success(message: 'Two-factor authentication disabled.');
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $recoveryCodes = $this->twoFactorService->regenerateRecoveryCodes($request->user());

        if ($recoveryCodes === null) {
            return $this->error(
                'Two-factor authentication is not enabled on this account.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                errorCode: 'two_factor_not_enabled',
            );
        }

        return $this->success(['recovery_codes' => $recoveryCodes]);
    }
}
