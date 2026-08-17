<?php

namespace Tests\Feature\Rbac;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_view_their_own_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($user->can('view', $tenant));
    }

    public function test_a_user_cannot_view_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenantA->id]);

        $this->assertFalse($user->can('view', $tenantB));
    }

    public function test_only_a_user_with_tenant_settings_update_can_update_the_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $owner->assignRole('owner');
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        $manager->assignRole('manager');

        $this->assertTrue($owner->can('update', $tenant));
        $this->assertFalse($manager->can('update', $tenant));
    }
}
