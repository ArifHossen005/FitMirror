<?php

namespace Tests\Feature\Plan;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanListTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_plan_list_is_reachable_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/plans');

        $response->assertOk();
        /** @var array<int, array<string, mixed>> $data */
        $data = $response->json('data');
        $slugs = collect($data)->pluck('slug');
        $this->assertSame(['free', 'pro', 'max'], $slugs->all());
    }

    public function test_each_plan_carries_its_limits_and_features(): void
    {
        $response = $this->getJson('/api/v1/plans');

        $response->assertOk();
        /** @var array<int, array<string, mixed>> $data */
        $data = $response->json('data');
        $pro = collect($data)->firstWhere('slug', 'pro');
        $this->assertSame(500, $pro['limits']['try_on_sessions_per_day']);
        $this->assertTrue($pro['features']['campaign_manager']['enabled']);
    }
}
