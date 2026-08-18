<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Plan\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoRenewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_toggle_auto_renew_off_and_back_on(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'two_factor_secret' => 'test-secret',
            'two_factor_confirmed_at' => now(),
        ]);
        $owner->assignRole('owner');
        $tenant->forceFill(['owner_id' => $owner->id])->save();
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();
        app(SubscriptionService::class)->startTrial($tenant, $pro);

        $token = $owner->createToken('t')->plainTextToken;

        $off = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/subscription/auto-renew', ['auto_renew' => false]);
        $off->assertOk();
        $off->assertJsonPath('data.auto_renew', false);

        $on = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/subscription/auto-renew', ['auto_renew' => true]);
        $on->assertOk();
        $on->assertJsonPath('data.auto_renew', true);
    }

    public function test_a_non_owner_cannot_toggle_auto_renew(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        $manager->assignRole('manager');

        $token = $manager->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/subscription/auto-renew', ['auto_renew' => false]);

        $response->assertForbidden();
    }

    public function test_toggling_with_no_subscription_returns_a_clear_404(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'two_factor_secret' => 'test-secret',
            'two_factor_confirmed_at' => now(),
        ]);
        $owner->assignRole('owner');
        $tenant->forceFill(['owner_id' => $owner->id])->save();

        $token = $owner->createToken('t')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/subscription/auto-renew', ['auto_renew' => false]);

        $response->assertNotFound();
        $response->assertJsonPath('error_code', 'no_active_subscription');
    }
}
