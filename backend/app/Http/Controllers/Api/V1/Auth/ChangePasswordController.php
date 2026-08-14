<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class ChangePasswordController extends BaseApiController
{
    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!Hash::check($request->validated('current_password'), $user->password)) {
            return $this->error(
                'Your current password is incorrect.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                errorCode: 'invalid_current_password',
            );
        }

        $user->forceFill(['password' => $request->validated('new_password')])->save();

        // Every OTHER session is revoked — the token used for this very
        // request stays valid so the client isn't logged out mid-flow by
        // its own successful change-password call.
        $currentTokenId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return $this->success(message: 'Password changed successfully.');
    }
}
