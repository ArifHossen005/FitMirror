<?php

namespace Tests\Feature\Plan;

use App\Models\Tenant;
use App\Models\User;
use App\Support\UsageCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanUsageEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_resolved_plan_and_usage_rows(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $owner->assignRole('owner');
        app(UsageCounter::class)->increment($tenant, 'try_on_sessions_per_day', 7);
        $token = $owner->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plan/usage');

        $response->assertOk();
        $response->assertJsonPath('data.plan.slug', 'pro');

        $rows = collect((array) $response->json('data.usage'))->keyBy('key');
        $this->assertSame(7, $rows['try_on_sessions_per_day']['current']);
        $this->assertSame(500, $rows['try_on_sessions_per_day']['limit']);
        // staff_accounts: only the owner exists — a live count, not the
        // Redis counter.
        $this->assertSame(1, $rows['staff_accounts']['current']);
        // Not trackable yet (Phase 5/4 tables don't exist) — never a
        // misleading 0.
        $this->assertNull($rows['categories']['current']);
    }

    public function test_unlimited_limits_are_flagged(): void
    {
        $tenant = Tenant::factory()->onPlan('max')->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $owner->assignRole('owner');
        $token = $owner->createToken('t')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plan/usage');

        $rows = collect((array) $response->json('data.usage'))->keyBy('key');
        $this->assertTrue($rows['staff_accounts']['unlimited']);
        $this->assertNull($rows['staff_accounts']['limit']);
    }
}
