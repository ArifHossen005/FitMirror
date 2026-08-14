<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

/**
 * POST /api/v1/auth/forgot-password. Always responds with the same
 * generic success message regardless of whether the email exists —
 * returning a different message for "no such account" would let an
 * attacker enumerate registered emails.
 */
class ForgotPasswordController extends BaseApiController
{
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return $this->success(
            message: 'If an account exists for that email, a password reset link has been sent.',
        );
    }
}
