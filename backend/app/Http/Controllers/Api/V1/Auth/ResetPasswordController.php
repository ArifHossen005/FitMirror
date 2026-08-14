<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Response;

class ResetPasswordController extends BaseApiController
{
    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();

                // A password reset means "I no longer trust whoever might
                // hold my old session" — every existing token is revoked,
                // not just the one (if any) making this request.
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error(
                trans($status),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                errorCode: 'password_reset_failed',
            );
        }

        return $this->success(message: 'Password reset successfully. Please log in with your new password.');
    }
}
