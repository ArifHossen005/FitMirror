<?php

namespace Tests\Feature\Billing;

use App\Exceptions\InsufficientAddonBalanceException;
use App\Models\Addon;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Services\Billing\AddonConsumptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddonConsumptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_sums_every_usable_row(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $addon = Addon::factory()->create(['code' => 'sms']);
        TenantAddon::factory()->for($tenant)->for($addon)->balance(100)->create();
        TenantAddon::factory()->for($tenant)->for($addon)->balance(50)->create();

        $this->assertSame(150, app(AddonConsumptionService::class)->balance($tenant, 'sms'));
    }

    public function test_expired_rows_are_excluded_from_balance(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $addon = Addon::factory()->create(['code' => 'sms']);
        TenantAddon::factory()->for($tenant)->for($addon)->balance(100)->expiresAt(now()->subDay())->create();
        TenantAddon::factory()->for($tenant)->for($addon)->balance(50)->create();

        $this->assertSame(50, app(AddonConsumptionService::class)->balance($tenant, 'sms'));
    }

    public function test_consume_draws_down_fifo_by_purchase_date(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $addon = Addon::factory()->create(['code' => 'sms']);
        $older = TenantAddon::factory()->for($tenant)->for($addon)->balance(30)->purchasedAt(now()->subDays(2))->create();
        $newer = TenantAddon::factory()->for($tenant)->for($addon)->balance(30)->purchasedAt(now()->subDay())->create();

        app(AddonConsumptionService::class)->consume($tenant, 'sms', 40);

        $this->assertSame(0, $older->fresh()->remaining_balance);
        $this->assertSame(20, $newer->fresh()->remaining_balance);
    }

    public function test_consuming_more_than_the_available_balance_throws_and_changes_nothing(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);
        $addon = Addon::factory()->create(['code' => 'sms']);
        $row = TenantAddon::factory()->for($tenant)->for($addon)->balance(10)->create();

        try {
            app(AddonConsumptionService::class)->consume($tenant, 'sms', 11);
            $this->fail('Expected InsufficientAddonBalanceException.');
        } catch (InsufficientAddonBalanceException $e) {
            $this->assertSame('sms', $e->addonCode);
            $this->assertSame(11, $e->requested);
            $this->assertSame(10, $e->available);
        }

        $this->assertSame(10, $row->fresh()->remaining_balance);
    }

    public function test_a_different_tenants_balance_is_never_drawn_down(): void
    {
        $tenantA = Tenant::factory()->create(['plan_id' => null]);
        $tenantB = Tenant::factory()->create(['plan_id' => null]);
        $addon = Addon::factory()->create(['code' => 'sms']);
        $rowA = TenantAddon::factory()->for($tenantA)->for($addon)->balance(100)->create();
        TenantAddon::factory()->for($tenantB)->for($addon)->balance(100)->create();

        app(AddonConsumptionService::class)->consume($tenantA, 'sms', 100);

        $this->assertSame(0, $rowA->fresh()->remaining_balance);
        $this->assertSame(100, app(AddonConsumptionService::class)->balance($tenantB, 'sms'));
    }
}
