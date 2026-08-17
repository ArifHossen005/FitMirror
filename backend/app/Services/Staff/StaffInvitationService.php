<?php

namespace App\Services\Staff;

use App\Enums\StaffInvitationStatus;
use App\Enums\UserStatus;
use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use App\Services\BaseService;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Invite/accept is deliberately two steps, not one — no User row exists
 * until the invitee actually accepts (sets their own password), so an
 * unopened invitation never counts as a seat, a login, or a staff record
 * anyone else can see in the staff list.
 */
class StaffInvitationService extends BaseService
{
    private const INVITATION_TTL_DAYS = 7;

    public function __construct(private readonly PlanService $plans) {}

    /**
     * Roles a tenant can invite someone *into*. 'owner' is deliberately
     * excluded — there is exactly one owner per tenant (Tenant::owner_id,
     * set only at registration), never assigned via the staff surface.
     *
     * @return list<string>
     */
    public static function invitableRoles(): array
    {
        return ['manager', 'staff'];
    }

    /**
     * @param array{name?: string, email: string, role: string} $data
     */
    public function invite(Tenant $tenant, User $inviter, array $data): StaffInvitation
    {
        if (!in_array($data['role'], self::invitableRoles(), true)) {
            throw ValidationException::withMessages([
                'role' => ['That role cannot be assigned via invitation.'],
            ]);
        }

        if (User::query()->where('tenant_id', $tenant->id)->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['A staff member with this email already exists.'],
            ]);
        }

        if (
            StaffInvitation::query()
                ->where('tenant_id', $tenant->id)
                ->where('email', $data['email'])
                ->where('status', StaffInvitationStatus::Pending)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'email' => ['An invitation is already pending for this email.'],
            ]);
        }

        // Counts existing seats (Users), not pending invitations — an
        // unopened invite doesn't hold a seat (see this class's own
        // docblock), so it shouldn't block a tenant from sending another.
        $currentSeats = User::query()->where('tenant_id', $tenant->id)->count();
        $this->plans->assertWithinLimit($tenant, 'staff_accounts', $currentSeats);

        return $this->transaction(function () use ($tenant, $inviter, $data) {
            ['token' => $rawToken, 'hash' => $hash] = StaffInvitation::generateToken();

            $invitation = StaffInvitation::query()->create([
                'tenant_id' => $tenant->id,
                'email' => $data['email'],
                'name' => $data['name'] ?? null,
                'role' => $data['role'],
                'token_hash' => $hash,
                'invited_by' => $inviter->id,
                'status' => StaffInvitationStatus::Pending,
                'expires_at' => now()->addDays(self::INVITATION_TTL_DAYS),
            ]);

            Notification::route('mail', $invitation->email)
                ->notify(new StaffInvitationNotification($invitation, $tenant, $rawToken));

            return $invitation;
        });
    }

    /**
     * @param array{name: string, password: string} $data
     * @return array{user: User, tenant: Tenant, token: string}
     */
    public function accept(string $rawToken, array $data): array
    {
        // withoutTenantScope(): the invitee has no session and therefore no
        // resolvable tenant context yet — same reasoning as
        // LoginService's initial credential lookup (see Decision D-13).
        $invitation = StaffInvitation::withoutTenantScope()
            ->where('token_hash', StaffInvitation::hashToken($rawToken))
            ->first();

        if (!$invitation) {
            throw ValidationException::withMessages([
                'token' => ['This invitation link is invalid.'],
            ]);
        }

        if ($invitation->status !== StaffInvitationStatus::Pending) {
            throw ValidationException::withMessages([
                'token' => ['This invitation has already been used or was revoked.'],
            ]);
        }

        if ($invitation->expires_at->isPast()) {
            $invitation->forceFill(['status' => StaffInvitationStatus::Expired])->save();

            throw ValidationException::withMessages([
                'token' => ['This invitation link has expired. Ask your tenant owner to send a new one.'],
            ]);
        }

        return $this->transaction(function () use ($invitation, $data) {
            $user = User::withoutTenantScope()->create([
                'tenant_id' => $invitation->tenant_id,
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => $data['password'],
                'status' => UserStatus::Active,
            ]);

            // Receiving the invitation email and clicking through with a
            // matching, unguessable token *is* proof of ownership of
            // $invitation->email — mass assignment can't set this directly
            // (email_verified_at is deliberately not in User::$fillable),
            // so markEmailAsVerified() is used instead of a raw attribute.
            $user->markEmailAsVerified();

            $user->assignRole($invitation->role);

            $invitation->forceFill([
                'status' => StaffInvitationStatus::Accepted,
                'accepted_at' => now(),
            ])->save();

            $tenant = $user->tenant;

            return [
                'user' => $user,
                'tenant' => $tenant,
                'token' => $user->createToken('auth', ['*'])->plainTextToken,
            ];
        });
    }

    public function revoke(StaffInvitation $invitation): void
    {
        if ($invitation->status !== StaffInvitationStatus::Pending) {
            throw ValidationException::withMessages([
                'invitation' => ['Only a pending invitation can be revoked.'],
            ]);
        }

        $invitation->forceFill([
            'status' => StaffInvitationStatus::Revoked,
            'revoked_at' => now(),
        ])->save();
    }
}
