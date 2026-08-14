<?php

namespace App\Services\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Credential verification, progressive lockout, 2FA branching, and Sanctum
 * token issuance for the tenant-facing login flow. Deliberately separate
 * from Mission Control's login (Api\V1\Mission\MissionAuthController) —
 * different guard, different model, no shared code beyond both hashing
 * passwords the same way.
 */
class LoginService extends BaseService
{
    /**
     * Failed attempts (since the last success) before an otherwise-correct
     * password is rejected. Progressive: the lockout window itself grows
     * with LOCKOUT_BASE_MINUTES * failures beyond the threshold, so a
     * script trying thousands of guesses faces an ever-longer wait, not a
     * fixed one.
     */
    private const LOCKOUT_THRESHOLD = 5;

    private const LOCKOUT_BASE_MINUTES = 5;

    private const TWO_FACTOR_CHALLENGE_TTL_MINUTES = 5;

    public function __construct(private readonly TwoFactorService $twoFactorService) {}

    /**
     * @return array{status: 'authenticated', user: User, token: string}|array{status: 'two_factor_required', two_factor_token: string}
     */
    public function attempt(string $email, string $password, string $ipAddress, ?string $userAgent): array
    {
        $this->guardAgainstLockout($email);

        // withoutTenantScope(): login is how the client finds out which
        // tenant it belongs to in the first place — no tenant context can
        // possibly be active yet at this exact lookup.
        $user = User::withoutTenantScope()->where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            LoginAttempt::record($email, $ipAddress, $userAgent, successful: false);

            throw ValidationException::withMessages([
                'email' => [trans('common.auth.invalid_credentials')],
            ]);
        }

        if (!$user->isActive()) {
            LoginAttempt::record($email, $ipAddress, $userAgent, successful: false);

            throw ValidationException::withMessages([
                'email' => [trans('common.tenant.suspended')],
            ]);
        }

        LoginAttempt::record($email, $ipAddress, $userAgent, successful: true);

        if ($user->hasTwoFactorEnabled()) {
            return [
                'status' => 'two_factor_required',
                'two_factor_token' => $this->issueTwoFactorChallenge($user),
            ];
        }

        return [
            'status' => 'authenticated',
            'user' => $user,
            'token' => $this->issueToken($user),
        ];
    }

    /**
     * @return array{user: User, token: string}
     */
    public function completeTwoFactorChallenge(string $twoFactorToken, ?string $code, ?string $recoveryCode): array
    {
        $userId = Cache::get($this->challengeCacheKey($twoFactorToken));

        if ($userId === null) {
            throw ValidationException::withMessages([
                'two_factor_token' => ['This login challenge has expired. Please log in again.'],
            ]);
        }

        $user = User::withoutTenantScope()->find($userId);

        if (!$user) {
            throw ValidationException::withMessages([
                'two_factor_token' => ['This login challenge has expired. Please log in again.'],
            ]);
        }

        $verified = ($code !== null && $this->twoFactorService->verifyCode($user, $code))
            || ($recoveryCode !== null && $this->twoFactorService->verifyAndConsumeRecoveryCode($user, $recoveryCode));

        if (!$verified) {
            throw ValidationException::withMessages([
                'code' => ['The provided two-factor code is invalid.'],
            ]);
        }

        Cache::forget($this->challengeCacheKey($twoFactorToken));

        return ['user' => $user, 'token' => $this->issueToken($user)];
    }

    private function guardAgainstLockout(string $email): void
    {
        $failures = LoginAttempt::consecutiveFailures($email);

        if ($failures < self::LOCKOUT_THRESHOLD) {
            return;
        }

        $lockedForMinutes = self::LOCKOUT_BASE_MINUTES * ($failures - self::LOCKOUT_THRESHOLD + 1);
        $lastAttempt = LoginAttempt::query()->where('email', $email)->latest('created_at')->first();

        if ($lastAttempt && $lastAttempt->created_at->addMinutes($lockedForMinutes)->isFuture()) {
            throw ValidationException::withMessages([
                'email' => ['Too many failed attempts. Please try again later.'],
            ]);
        }
    }

    private function issueToken(User $user): string
    {
        $user->forceFill(['last_login_at' => now()])->save();

        return $user->createToken('auth', ['*'])->plainTextToken;
    }

    private function issueTwoFactorChallenge(User $user): string
    {
        $token = Str::random(64);

        Cache::put($this->challengeCacheKey($token), $user->id, now()->addMinutes(self::TWO_FACTOR_CHALLENGE_TTL_MINUTES));

        return $token;
    }

    private function challengeCacheKey(string $token): string
    {
        return "auth:2fa-challenge:{$token}";
    }
}
