<?php

namespace App\Services\Mission;

use App\Models\Activity;
use App\Models\Impersonation;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Validation\ValidationException;

/**
 * Issues and revokes the short-lived Sanctum token a super admin uses to
 * act as a tenant user for support/debugging. Every issuance and exit is
 * both a row in `impersonations` (queryable audit trail) and an
 * App\Models\Activity entry (visible in the same audit log the tenant
 * itself can see via GET /api/v1/audit-log — full transparency that an
 * impersonation happened, not a silent backdoor).
 */
class ImpersonationService extends BaseService
{
    private const TTL_MINUTES = 30;

    /**
     * @return array{token: string, impersonation: Impersonation}
     */
    public function start(SuperAdmin $superAdmin, User $target, ?string $reason, string $ip, ?string $userAgent): array
    {
        if ($target->tenant_id === null) {
            throw ValidationException::withMessages([
                'user' => ['This account has no tenant to impersonate into.'],
            ]);
        }

        return $this->transaction(function () use ($superAdmin, $target, $reason, $ip, $userAgent) {
            $startedAt = now();
            $expiresAt = $startedAt->copy()->addMinutes(self::TTL_MINUTES);

            $token = $target->createToken('impersonation', ['impersonation'], $expiresAt);

            $impersonation = Impersonation::query()->create([
                'super_admin_id' => $superAdmin->id,
                'tenant_id' => $target->tenant_id,
                'user_id' => $target->id,
                'token_id' => $token->accessToken->id,
                'reason' => $reason,
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);

            activity('impersonation')
                ->performedOn($target)
                ->causedBy($superAdmin)
                ->withProperties(['reason' => $reason, 'impersonation_id' => $impersonation->id])
                ->tap(function (Activity $activity) use ($target) {
                    $activity->tenant_id = $target->tenant_id;
                })
                ->log('Super admin started impersonating this account.');

            return ['token' => $token->plainTextToken, 'impersonation' => $impersonation];
        });
    }

    /**
     * Ends the impersonation session identified by the *current* Sanctum
     * token — called from inside the impersonated session itself
     * (ImpersonationExitController), so there is no ambiguity about which
     * session is being ended.
     */
    public function end(User $impersonatedUser, int $currentTokenId): void
    {
        $impersonation = Impersonation::query()
            ->where('token_id', $currentTokenId)
            ->whereNull('ended_at')
            ->first();

        $this->transaction(function () use ($impersonatedUser, $currentTokenId, $impersonation) {
            $impersonatedUser->tokens()->where('id', $currentTokenId)->delete();

            if ($impersonation === null) {
                return;
            }

            $impersonation->forceFill(['ended_at' => now()])->save();

            activity('impersonation')
                ->performedOn($impersonatedUser)
                ->withProperties(['impersonation_id' => $impersonation->id])
                ->tap(function (Activity $activity) use ($impersonatedUser) {
                    $activity->tenant_id = $impersonatedUser->tenant_id;
                })
                ->log('Impersonation session ended.');
        });
    }
}
