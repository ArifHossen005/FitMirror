<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * TOTP 2FA — enable/confirm/disable and recovery codes. Shared between
 * Api\V1\Auth\TwoFactorController (tenant users) and, once Mission Control
 * grows its own 2FA setup UI, SuperAdmin — the underlying secret/recovery
 * code storage shape is identical on both models, so this only depends on
 * the columns, not on which guard the caller authenticates through.
 */
class TwoFactorService extends BaseService
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Starts setup: generates a new secret and stores it unconfirmed. The
     * caller must still call confirm() with a valid code before 2FA is
     * actually enforced on login — storing the secret alone doesn't turn
     * 2FA on (see User::hasTwoFactorEnabled(), which also requires
     * two_factor_confirmed_at).
     *
     * @return array{secret: string, otpauth_url: string, qr_code_svg: string}
     */
    public function startSetup(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $otpAuthUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        return [
            'secret' => $secret,
            'otpauth_url' => $otpAuthUrl,
            'qr_code_svg' => QrCode::size(200)->generate($otpAuthUrl),
        ];
    }

    /**
     * Confirms setup with a real code from the authenticator app.
     * Generates and returns recovery codes in plaintext exactly once —
     * only the hashed form is ever persisted, matching how passwords are
     * stored.
     *
     * @return list<string>|null Plaintext recovery codes, or null if $code was invalid.
     */
    public function confirm(User $user, string $code): ?array
    {
        if ($user->two_factor_secret === null || !$this->verifyCode($user, $code)) {
            return null;
        }

        $plainCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(fn (string $c) => Hash::make($c), $plainCodes),
        ])->save();

        return $plainCodes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * @return list<string>|null Plaintext recovery codes, or null if 2FA isn't confirmed on this account.
     */
    public function regenerateRecoveryCodes(User $user): ?array
    {
        if (!$user->hasTwoFactorEnabled()) {
            return null;
        }

        $plainCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $c) => Hash::make($c), $plainCodes),
        ])->save();

        return $plainCodes;
    }

    public function verifyCode(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        return $this->google2fa->verifyKey($user->two_factor_secret, $code);
    }

    /**
     * Consumes a recovery code — each one works exactly once. Returns
     * false without consuming anything if $code doesn't match any stored
     * hash.
     */
    public function verifyAndConsumeRecoveryCode(User $user, string $code): bool
    {
        $hashedCodes = $user->two_factor_recovery_codes ?? [];

        foreach ($hashedCodes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                unset($hashedCodes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($hashedCodes)])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        return array_map(
            fn () => Str::lower(Str::random(4)) . '-' . Str::lower(Str::random(4)),
            range(1, self::RECOVERY_CODE_COUNT),
        );
    }
}
