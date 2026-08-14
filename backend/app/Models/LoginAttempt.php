<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail of every login attempt (successful or not),
 * keyed on the submitted email rather than a user_id FK so a guess against
 * a nonexistent email is still recorded. Backs both the progressive
 * account-lockout check (App\Services\Auth\LoginService) and a future
 * Phase 2.C "audit log" view.
 */
class LoginAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = ['email', 'ip_address', 'user_agent', 'successful', 'created_at'];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public static function record(string $email, string $ipAddress, ?string $userAgent, bool $successful): void
    {
        static::query()->create([
            'email' => $email,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'successful' => $successful,
            'created_at' => now(),
        ]);
    }

    /**
     * Count of consecutive failed attempts for $email since its last
     * success (or since the beginning of history, if it has never
     * succeeded) — used for progressive lockout rather than a fixed
     * rolling window, so a single old failure from months ago never counts
     * against a currently-healthy account.
     */
    public static function consecutiveFailures(string $email): int
    {
        $lastSuccess = static::query()
            ->where('email', $email)
            ->where('successful', true)
            ->latest('created_at')
            ->first();

        return static::query()
            ->where('email', $email)
            ->where('successful', false)
            ->when($lastSuccess, fn ($query) => $query->where('created_at', '>', $lastSuccess->created_at))
            ->count();
    }
}
