<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/auth/logout — revokes only the token used on this request,
 * mirroring Mission Control's logout (Api\V1\Mission\MissionAuthController).
 * A user logged in on two devices logging out of one never logs out the
 * other.
 */
class LogoutController extends BaseApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->noContent();
    }
}
