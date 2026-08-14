<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class EmailVerificationController extends BaseApiController
{
    /**
     * GET /api/v1/auth/email/verify/{id}/{hash} — the link
     * Illuminate\Auth\Notifications\VerifyEmail sends. Named
     * `verification.verify` (see routes/api_v1.php) because that
     * notification hardcodes that route name when building the signed
     * URL — renaming the route silently breaks every already-sent email.
     */
    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        // withoutTenantScope(): the signed link carries only a user id and
        // hash, resolved before any tenant context can exist — same
        // reasoning as LoginService's credential lookup.
        $user = User::withoutTenantScope()->findOrFail($id);

        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return $this->error(
                'This verification link is invalid.',
                Response::HTTP_FORBIDDEN,
                errorCode: 'invalid_verification_link',
            );
        }

        if (!URL::hasValidSignature($request)) {
            return $this->error(
                'This verification link has expired. Please request a new one.',
                Response::HTTP_FORBIDDEN,
                errorCode: 'expired_verification_link',
            );
        }

        if ($user->hasVerifiedEmail()) {
            return $this->success(message: 'Email already verified.');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return $this->success(message: 'Email verified successfully.');
    }

    /**
     * POST /api/v1/auth/email/resend — authenticated, throttled via the
     * `auth` limiter so it can't be used to spam an inbox.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->success(message: 'Email already verified.');
        }

        $user->sendEmailVerificationNotification();

        return $this->success(message: 'Verification link sent.');
    }
}
