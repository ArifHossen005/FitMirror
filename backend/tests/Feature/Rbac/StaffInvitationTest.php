<?php

namespace Tests\Feature\Rbac;

use App\Enums\StaffInvitationStatus;
use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response('', 200)]);
    }

    private function owner(Tenant $tenant): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('owner');

        return $user;
    }

    public function test_invite_sends_a_notification_and_creates_a_pending_invitation(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->onPlan('max')->create();
        $owner = $this->owner($tenant);
        $token = $owner->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/staff/invitations', [
            'name' => 'New Staffer',
            'email' => 'staffer@example.com',
            'role' => 'manager',
        ]);

        $response->assertCreated();

        $invitation = StaffInvitation::query()->where('email', 'staffer@example.com')->firstOrFail();
        $this->assertSame($tenant->id, $invitation->tenant_id);
        $this->assertSame(StaffInvitationStatus::Pending, $invitation->status);

        Notification::assertSentOnDemand(StaffInvitationNotification::class);
    }

    public function test_invite_rejects_an_email_that_already_belongs_to_a_staff_member(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->owner($tenant);
        User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'existing@example.com']);
        $token = $owner->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/staff/invitations', [
            'email' => 'existing@example.com',
            'role' => 'staff',
        ]);

        $response->assertStatus(422);
    }

    public function test_invite_is_blocked_once_the_plans_staff_account_limit_is_reached(): void
    {
        // Free plan: staff_accounts = 1, already spent by the owner.
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $owner = $this->owner($tenant);
        $token = $owner->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/staff/invitations', [
            'email' => 'onemore@example.com',
            'role' => 'staff',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('error_code', 'plan_limit_exceeded');
        $response->assertJsonPath('errors.limit', 'staff_accounts');
        $response->assertJsonPath('errors.max', 1);
    }

    public function test_invite_rejects_the_owner_role(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->owner($tenant);
        $token = $owner->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/staff/invitations', [
            'email' => 'wannabe-owner@example.com',
            'role' => 'owner',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['role']);
    }

    public function test_accepting_a_valid_invitation_creates_an_active_user_with_the_assigned_role(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->owner($tenant);
        ['token' => $rawToken, 'hash' => $hash] = StaffInvitation::generateToken();

        $invitation = StaffInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'email' => 'invitee@example.com',
            'role' => 'manager',
            'token_hash' => $hash,
            'invited_by' => $owner->id,
            'status' => StaffInvitationStatus::Pending,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $rawToken,
            'name' => 'Invitee Person',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['token', 'user', 'tenant']]);

        $user = User::withoutTenantScope()->where('email', 'invitee@example.com')->firstOrFail();
        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertTrue($user->hasRole('manager'));
        $this->assertTrue($user->hasVerifiedEmail());

        $this->assertSame(StaffInvitationStatus::Accepted, $invitation->fresh()->status);
    }

    public function test_accepting_with_an_invalid_token_fails(): void
    {
        $response = $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => 'not-a-real-token',
            'name' => 'Nobody',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
        ]);

        $response->assertStatus(422);
    }

    public function test_accepting_an_expired_invitation_fails_and_marks_it_expired(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->owner($tenant);
        ['token' => $rawToken, 'hash' => $hash] = StaffInvitation::generateToken();

        $invitation = StaffInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'email' => 'toolate@example.com',
            'role' => 'staff',
            'token_hash' => $hash,
            'invited_by' => $owner->id,
            'status' => StaffInvitationStatus::Pending,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $rawToken,
            'name' => 'Nobody',
            'password' => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
        ]);

        $response->assertStatus(422);
        $this->assertSame(StaffInvitationStatus::Expired, $invitation->fresh()->status);
        $this->assertDatabaseMissing('users', ['email' => 'toolate@example.com']);
    }

    public function test_revoking_a_pending_invitation_removes_it_from_the_pending_list(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->owner($tenant);
        $invitation = StaffInvitation::factory()->for($tenant)->create(['invited_by' => $owner->id]);
        $token = $owner->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/staff/invitations/{$invitation->id}")
            ->assertNoContent();

        $this->assertSame(StaffInvitationStatus::Revoked, $invitation->fresh()->status);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/staff/invitations')
            ->assertJsonCount(0, 'data');
    }
}
