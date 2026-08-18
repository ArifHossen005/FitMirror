<?php

namespace Tests\Feature\Plan;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Plan\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_tenants_current_subscription(): void
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
        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/subscription');

        $response->assertOk();
        $response->assertJsonPath('data.status', SubscriptionStatus::Trialing->value);
        $response->assertJsonPath('data.auto_renew', true);
    }

    public function test_returns_null_data_when_the_tenant_has_no_subscription(): void
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
        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/subscription');

        $response->assertOk();
        $response->assertJsonPath('data', null);
    }
}
