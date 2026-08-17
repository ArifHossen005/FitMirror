<?php

namespace Tests\Feature\Rbac;

use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffCrudTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A user with the 'owner' RBAC role but *not* Tenant::owner_id — used
     * as the acting caller in most tests below, since EnsureTwoFactorIsEnabled
     * only mandates 2FA for the literal tenant owner (isTenantOwner()), not
     * every account with the 'owner' permission set. See realOwnerFor()
     * for tests that specifically need genuine ownership immutability.
     */
    private function ownerFor(Tenant $tenant): User
    {
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $owner->assignRole('owner');

        return $owner;
    }

    private function realOwnerFor(Tenant $tenant): User
    {
        $owner = $this->ownerFor($tenant);
        $tenant->forceFill(['owner_id' => $owner->id])->save();

        return $owner;
    }

    /**
     * @return array<string, string>
     */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('t')->plainTextToken];
    }

    public function test_owner_can_update_a_staff_members_role(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->ownerFor($tenant);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $staff->assignRole('staff');

        $response = $this->withHeaders($this->bearer($owner))
            ->patchJson("/api/v1/staff/{$staff->id}/role", ['role' => 'manager']);

        $response->assertOk();
        $this->assertTrue($staff->fresh()->hasRole('manager'));
        $this->assertFalse($staff->fresh()->hasRole('staff'));
    }

    public function test_deactivating_a_staff_member_revokes_their_tokens(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->ownerFor($tenant);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $staff->assignRole('staff');
        $staff->createToken('existing');

        $response = $this->withHeaders($this->bearer($owner))->postJson("/api/v1/staff/{$staff->id}/deactivate");

        $response->assertOk();
        $this->assertSame(UserStatus::Suspended, $staff->fresh()->status);
        $this->assertSame(0, $staff->fresh()->tokens()->count());
    }

    public function test_the_tenant_owner_cannot_be_deactivated_by_a_manager(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->realOwnerFor($tenant);
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        $manager->assignRole('manager');

        $response = $this->withHeaders($this->bearer($manager))->postJson("/api/v1/staff/{$owner->id}/deactivate");

        $response->assertForbidden();
    }

    public function test_a_staff_member_cannot_deactivate_themselves(): void
    {
        $tenant = Tenant::factory()->create();
        $this->realOwnerFor($tenant);
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        $manager->assignRole('manager');

        $response = $this->withHeaders($this->bearer($manager))->postJson("/api/v1/staff/{$manager->id}/deactivate");

        $response->assertForbidden();
    }

    public function test_deleting_a_staff_member_soft_deletes_and_revokes_tokens(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->ownerFor($tenant);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $staff->assignRole('staff');
        $staff->createToken('existing');

        $response = $this->withHeaders($this->bearer($owner))->deleteJson("/api/v1/staff/{$staff->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $staff->id]);
    }

    public function test_staff_list_only_shows_users_in_the_callers_own_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $ownerA = $this->ownerFor($tenantA);
        User::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        User::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

        $response = $this->withHeaders($this->bearer($ownerA))->getJson('/api/v1/staff');

        $response->assertOk();
        // 2 seeded + the owner itself = 3.
        $response->assertJsonCount(3, 'data');
    }
}
