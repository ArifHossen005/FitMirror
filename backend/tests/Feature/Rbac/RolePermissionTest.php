<?php

namespace Tests\Feature\Rbac;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Asserts each seeded role's allowed/denied actions against the staff
 * management surface — the concrete, current stand-in for "every module",
 * since staff.* is the only real business permission group with a route
 * behind it today (Phase 2.C). Uses spatie roles directly, deliberately
 * decoupled from Tenant::owner_id — see this file's own test bodies for
 * why (EnsureTwoFactorIsEnabled only gates the *actual* tenant owner, not
 * every 'owner'-permissioned account).
 */
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(Tenant $tenant, string $role): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function bearer(User $user): array
    {
        $token = $user->createToken('t')->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_owner_can_invite_staff(): void
    {
        // 'max' plan: Free's single staff_accounts seat is already spent
        // by the owner themselves — this test is about the *permission*
        // check, not plan limits (that's PlanTest's job).
        $tenant = Tenant::factory()->onPlan('max')->create();
        $owner = $this->actingUser($tenant, 'owner');

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/staff/invitations', [
            'email' => 'newstaff@example.com',
            'role' => 'staff',
        ]);

        $response->assertCreated();
    }

    public function test_staff_role_cannot_invite_staff(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = $this->actingUser($tenant, 'staff');

        $response = $this->withHeaders($this->bearer($staff))->postJson('/api/v1/staff/invitations', [
            'email' => 'newstaff@example.com',
            'role' => 'staff',
        ]);

        $response->assertForbidden();
    }

    public function test_manager_can_view_staff_list_but_cannot_delete_staff(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->actingUser($tenant, 'manager');
        $target = $this->actingUser($tenant, 'staff');

        $this->withHeaders($this->bearer($manager))->getJson('/api/v1/staff')->assertOk();

        $this->withHeaders($this->bearer($manager))
            ->deleteJson("/api/v1/staff/{$target->id}")
            ->assertForbidden();
    }

    public function test_owner_can_delete_staff(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->actingUser($tenant, 'owner');
        $target = $this->actingUser($tenant, 'staff');

        $this->withHeaders($this->bearer($owner))
            ->deleteJson("/api/v1/staff/{$target->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_staff_role_cannot_view_audit_log(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = $this->actingUser($tenant, 'staff');

        $this->withHeaders($this->bearer($staff))->getJson('/api/v1/audit-log')->assertForbidden();
    }

    public function test_manager_can_view_audit_log(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->actingUser($tenant, 'manager');

        $this->withHeaders($this->bearer($manager))->getJson('/api/v1/audit-log')->assertOk();
    }

    public function test_me_endpoint_reports_role_and_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->actingUser($tenant, 'manager');

        $response = $this->withHeaders($this->bearer($manager))->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonPath('data.roles.0', 'manager');
        $this->assertContains('staff.invite', $response->json('data.permissions'));
        $this->assertNotContains('billing.manage', $response->json('data.permissions'));
    }

    public function test_a_staff_user_cannot_act_on_a_user_in_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $owner = $this->actingUser($tenantA, 'owner');
        $otherTenantUser = $this->actingUser($tenantB, 'staff');

        $this->withHeaders($this->bearer($owner))
            ->getJson("/api/v1/staff/{$otherTenantUser->id}")
            ->assertNotFound();
    }
}
