<?php

namespace Tests\Feature\Store;

use App\Enums\StoreStatus;
use App\Models\FranchiseGroup;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Franchise roll-up. Two things matter here: the plan gate (Max only), and
 * that the consolidated view genuinely reaches across tenants without
 * TenantScope hiding the members — a franchisor's whole reason for the
 * feature is seeing shops that are not its own.
 */
class FranchiseGroupTest extends TestCase
{
    use RefreshDatabase;

    private function ownerFor(Tenant $tenant): User
    {
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $owner->assignRole('owner');

        return $owner;
    }

    /**
     * @return array<string, string>
     */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('t')->plainTextToken];
    }

    public function test_franchise_groups_are_gated_to_plans_that_include_the_feature(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);

        $this->withHeaders($this->bearer($owner))
            ->getJson('/api/v1/franchise-groups')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'plan_feature_unavailable')
            ->assertJsonPath('errors.feature', 'franchise_management');
    }

    public function test_creating_a_group_always_includes_the_franchisors_own_shop(): void
    {
        $tenant = Tenant::factory()->onPlan('max')->create();
        $owner = $this->ownerFor($tenant);

        $response = $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/franchise-groups', ['name' => 'Northern Region']);

        $response->assertCreated();
        $response->assertJsonPath('data.totals.tenants', 1);
        $response->assertJsonPath('data.members.0.is_group_owner', true);
        $response->assertJsonPath('data.group.slug', 'northern-region');
    }

    public function test_the_consolidated_view_counts_stores_and_staff_across_member_tenants(): void
    {
        $franchisor = Tenant::factory()->onPlan('max')->create();
        $owner = $this->ownerFor($franchisor);
        Store::factory()->count(2)->create(['tenant_id' => $franchisor->id]);

        $franchiseeA = Tenant::factory()->create();
        Store::factory()->count(3)->create(['tenant_id' => $franchiseeA->id]);
        User::factory()->count(4)->create(['tenant_id' => $franchiseeA->id]);

        $franchiseeB = Tenant::factory()->create();
        Store::factory()->create(['tenant_id' => $franchiseeB->id]);

        $response = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/franchise-groups', [
            'name' => 'All Shops',
            'member_tenant_ids' => [$franchiseeA->id, $franchiseeB->id],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.totals.tenants', 3);
        // 2 (franchisor) + 3 + 1 — proof the cross-tenant read is not being
        // filtered down to the acting tenant by TenantScope.
        $response->assertJsonPath('data.totals.stores', 6);
        // 1 owner + 4 = 5.
        $response->assertJsonPath('data.totals.staff', 5);
    }

    public function test_a_closed_branch_is_excluded_from_the_roll_up(): void
    {
        $franchisor = Tenant::factory()->onPlan('max')->create();
        $owner = $this->ownerFor($franchisor);
        Store::factory()->create(['tenant_id' => $franchisor->id]);
        Store::factory()->status(StoreStatus::Closed)->create(['tenant_id' => $franchisor->id]);

        $response = $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/franchise-groups', ['name' => 'Region']);

        $response->assertCreated();
        $response->assertJsonPath('data.totals.stores', 1);
    }

    public function test_members_can_be_added_and_removed(): void
    {
        $franchisor = Tenant::factory()->onPlan('max')->create();
        $owner = $this->ownerFor($franchisor);
        $franchisee = Tenant::factory()->create();

        $group = $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/franchise-groups', ['name' => 'Region']);
        $group->assertCreated();
        $groupId = $group->json('data.group.id');

        $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/franchise-groups/{$groupId}/members", [
                'member_tenant_id' => $franchisee->id,
                'label' => 'Chattogram partner',
            ])
            ->assertOk()
            ->assertJsonPath('data.totals.tenants', 2);

        // Adding the same shop twice is a no-op, not a duplicate row.
        $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/franchise-groups/{$groupId}/members", ['member_tenant_id' => $franchisee->id])
            ->assertOk()
            ->assertJsonPath('data.totals.tenants', 2);

        $this->withHeaders($this->bearer($owner))
            ->deleteJson("/api/v1/franchise-groups/{$groupId}/members/{$franchisee->id}")
            ->assertOk()
            ->assertJsonPath('data.totals.tenants', 1);
    }

    public function test_the_group_owner_cannot_be_removed_from_its_own_group(): void
    {
        $franchisor = Tenant::factory()->onPlan('max')->create();
        $owner = $this->ownerFor($franchisor);

        $group = $this->withHeaders($this->bearer($owner))
            ->postJson('/api/v1/franchise-groups', ['name' => 'Region']);
        $groupId = $group->json('data.group.id');

        $this->withHeaders($this->bearer($owner))
            ->deleteJson("/api/v1/franchise-groups/{$groupId}/members/{$franchisor->id}")
            ->assertStatus(422);
    }

    public function test_a_franchisor_cannot_open_another_franchisors_group(): void
    {
        $foreign = Tenant::factory()->onPlan('max')->create();
        $foreignGroup = FranchiseGroup::factory()->create(['tenant_id' => $foreign->id]);

        $tenant = Tenant::factory()->onPlan('max')->create();
        $owner = $this->ownerFor($tenant);

        // FranchiseGroup carries TenantScope, so a group owned by another
        // franchisor is not addressable at all.
        $this->withHeaders($this->bearer($owner))
            ->getJson("/api/v1/franchise-groups/{$foreignGroup->id}/overview")
            ->assertNotFound();
    }

    public function test_deleting_a_group_leaves_its_member_tenants_untouched(): void
    {
        $franchisor = Tenant::factory()->onPlan('max')->create();
        $owner = $this->ownerFor($franchisor);
        $franchisee = Tenant::factory()->create();

        $group = $this->withHeaders($this->bearer($owner))->postJson('/api/v1/franchise-groups', [
            'name' => 'Region',
            'member_tenant_ids' => [$franchisee->id],
        ]);
        $groupId = $group->json('data.group.id');

        $this->withHeaders($this->bearer($owner))
            ->deleteJson("/api/v1/franchise-groups/{$groupId}")
            ->assertNoContent();

        $this->assertDatabaseHas('tenants', ['id' => $franchisee->id, 'deleted_at' => null]);
        $this->assertDatabaseMissing('franchise_group_members', ['franchise_group_id' => $groupId]);
    }
}
