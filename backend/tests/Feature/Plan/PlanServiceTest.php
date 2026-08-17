<?php

namespace Tests\Feature\Plan;

use App\Exceptions\PlanLimitExceededException;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Plan\FeatureGate;
use App\Services\Plan\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tenant_with_no_plan_resolves_to_free(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);

        $plan = app(PlanService::class)->resolve($tenant);

        $this->assertSame('free', $plan->slug);
    }

    public function test_a_tenant_with_a_plan_resolves_to_it(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();

        $plan = app(PlanService::class)->resolve($tenant);

        $this->assertSame('pro', $plan->slug);
    }

    public function test_free_plan_limits_match_the_product_document(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $service = app(PlanService::class);

        $this->assertSame(50, $service->limit($tenant, 'try_on_sessions_per_day'));
        $this->assertSame(2, $service->limit($tenant, 'categories'));
        $this->assertSame(50, $service->limit($tenant, 'skus'));
        $this->assertSame(1, $service->limit($tenant, 'staff_accounts'));
        $this->assertSame(5, $service->limit($tenant, 'storage_gb'));
        $this->assertSame(1, $service->limit($tenant, 'branches'));
    }

    public function test_max_plan_limits_are_unlimited_except_storage(): void
    {
        $tenant = Tenant::factory()->onPlan('max')->create();
        $service = app(PlanService::class);

        $this->assertNull($service->limit($tenant, 'try_on_sessions_per_day'));
        $this->assertNull($service->limit($tenant, 'categories'));
        $this->assertNull($service->limit($tenant, 'staff_accounts'));
        $this->assertSame(500, $service->limit($tenant, 'storage_gb'));
    }

    public function test_within_limit_treats_unlimited_as_always_true(): void
    {
        $tenant = Tenant::factory()->onPlan('max')->create();

        $this->assertTrue(app(PlanService::class)->withinLimit($tenant, 'staff_accounts', 999_999));
    }

    public function test_assert_within_limit_throws_once_the_count_reaches_the_cap(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]); // Free: staff_accounts = 1

        $this->expectException(PlanLimitExceededException::class);

        app(PlanService::class)->assertWithinLimit($tenant, 'staff_accounts', 1);
    }

    public function test_forget_clears_the_cached_resolution(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $service = app(PlanService::class);

        $this->assertSame('free', $service->resolve($tenant)->slug);

        $tenant->forceFill(['plan_id' => Plan::query()->where('slug', 'max')->firstOrFail()->id])->save();
        $service->forget($tenant);

        $this->assertSame('max', $service->resolve($tenant->fresh())->slug);
    }

    public function test_feature_gate_reflects_the_resolved_plans_features(): void
    {
        $free = Tenant::factory()->create(['plan_id' => null]);
        $max = Tenant::factory()->onPlan('max')->create();
        $gate = app(FeatureGate::class);

        $this->assertFalse($gate->allows($free, 'campaign_manager'));
        $this->assertTrue($gate->allows($max, 'campaign_manager'));
        $this->assertSame('full_ai', $gate->tier($max, 'campaign_manager'));
        $this->assertSame('basic', $gate->tier($free, 'analytics'));
    }
}
