<?php

namespace Tests\Feature\Plan;

use App\Models\Tenant;
use App\Support\UsageCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageCounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_increment_and_current_round_trip(): void
    {
        $tenant = Tenant::factory()->create();
        $counter = app(UsageCounter::class);

        $counter->increment($tenant, 'try_on_sessions_per_day');
        $counter->increment($tenant, 'try_on_sessions_per_day', 4);

        $this->assertSame(5, $counter->current($tenant, 'try_on_sessions_per_day'));
    }

    public function test_counters_for_different_tenants_never_collide(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $counter = app(UsageCounter::class);

        $counter->increment($tenantA, 'try_on_sessions_per_day', 3);
        $counter->increment($tenantB, 'try_on_sessions_per_day', 9);

        $this->assertSame(3, $counter->current($tenantA, 'try_on_sessions_per_day'));
        $this->assertSame(9, $counter->current($tenantB, 'try_on_sessions_per_day'));
    }

    public function test_the_usage_reset_command_zeroes_every_counter(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $counter = app(UsageCounter::class);
        $counter->increment($tenantA, 'try_on_sessions_per_day', 3);
        $counter->increment($tenantB, 'sms_sent', 2);

        $this->artisan('usage:reset')->assertExitCode(0);

        $this->assertSame(0, $counter->current($tenantA, 'try_on_sessions_per_day'));
        $this->assertSame(0, $counter->current($tenantB, 'sms_sent'));
    }
}
