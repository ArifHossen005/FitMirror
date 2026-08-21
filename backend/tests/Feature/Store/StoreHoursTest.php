<?php

namespace Tests\Feature\Store;

use App\Models\Store;
use App\Models\StoreHour;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Store\StoreHoursService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/**
 * Opening hours, and the timezone/overnight reasoning the kiosk guard
 * depends on. Storage is UTC (Decision D-07) while the rows hold wall
 * clock times in the branch's own zone, so every case here is really
 * asking "did the conversion happen".
 */
class StoreHoursTest extends TestCase
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

    /**
     * The tests below call StoreHoursService directly rather than through
     * an HTTP request, so nothing has run ResolveTenant. StoreHour carries
     * TenantScope, which fails closed (Decision D-13) — without a context
     * every hours query returns zero rows and the service correctly reports
     * an unconfigured branch as always open, which is not what these cases
     * are trying to exercise.
     */
    private function actingAsTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant);
    }

    public function test_the_hours_endpoint_always_returns_all_seven_days(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->bearer($owner))
            ->getJson("/api/v1/stores/{$store->id}/hours");

        $response->assertOk();
        $response->assertJsonCount(7, 'data.days');
        $response->assertJsonPath('data.is_configured', false);
        $response->assertJsonPath('data.days.0.day_name', 'Sunday');
    }

    public function test_owner_can_replace_the_week(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);

        $days = [];

        for ($day = 0; $day <= 6; $day++) {
            $days[] = $day === 5
                ? ['day_of_week' => $day, 'is_closed' => true]
                : [
                    'day_of_week' => $day,
                    'is_closed' => false,
                    'opens_at' => '10:00',
                    'closes_at' => '22:00',
                    'kiosk_opens_at' => '11:00',
                    'kiosk_closes_at' => '20:00',
                ];
        }

        $response = $this->withHeaders($this->bearer($owner))
            ->putJson("/api/v1/stores/{$store->id}/hours", ['days' => $days]);

        $response->assertOk();
        $response->assertJsonPath('data.is_configured', true);
        $response->assertJsonPath('data.days.5.is_closed', true);
        // A closed day carries no times at all.
        $response->assertJsonPath('data.days.5.opens_at', null);
        $response->assertJsonPath('data.days.1.kiosk_opens_at', '11:00:00');

        $this->assertSame(7, StoreHour::query()->where('store_id', $store->id)->count());
    }

    public function test_an_open_day_missing_a_closing_time_is_rejected(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->bearer($owner))
            ->putJson("/api/v1/stores/{$store->id}/hours", [
                'days' => [['day_of_week' => 1, 'is_closed' => false, 'opens_at' => '10:00']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('days');
    }

    public function test_a_single_kiosk_time_without_its_pair_is_rejected(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->bearer($owner))
            ->putJson("/api/v1/stores/{$store->id}/hours", [
                'days' => [[
                    'day_of_week' => 1,
                    'is_closed' => false,
                    'opens_at' => '10:00',
                    'closes_at' => '22:00',
                    'kiosk_opens_at' => '11:00',
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('days');
    }

    public function test_a_duplicated_day_is_rejected(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->bearer($owner))
            ->putJson("/api/v1/stores/{$store->id}/hours", [
                'days' => [
                    ['day_of_week' => 1, 'is_closed' => true],
                    ['day_of_week' => 1, 'is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '17:00'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('days');
    }

    public function test_a_branch_with_no_hours_configured_is_always_open_to_its_kiosk(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $this->actingAsTenant($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);

        // Hours are an opt-in restriction — defaulting an unconfigured
        // branch to "closed" would brick every kiosk the moment it paired.
        $this->assertTrue(app(StoreHoursService::class)->kioskIsOpen($store));
    }

    public function test_kiosk_hours_are_evaluated_in_the_branchs_own_timezone(): void
    {
        // 04:00 UTC is 10:00 in Dhaka — inside the window — and 13:00 in
        // Auckland, which is outside it. Same instant, two answers.
        Date::setTestNow(CarbonImmutable::parse('2026-08-26 04:00:00', 'UTC'));

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $this->actingAsTenant($tenant);
        $hours = app(StoreHoursService::class);

        $dhaka = Store::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Asia/Dhaka']);
        StoreHour::factory()->forDay(3)->create([
            'store_id' => $dhaka->id,
            'tenant_id' => $tenant->id,
            'opens_at' => '09:00:00',
            'closes_at' => '12:00:00',
        ]);

        $auckland = Store::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Pacific/Auckland']);
        StoreHour::factory()->forDay(3)->create([
            'store_id' => $auckland->id,
            'tenant_id' => $tenant->id,
            'opens_at' => '09:00:00',
            'closes_at' => '12:00:00',
        ]);

        $this->assertTrue($hours->kioskIsOpen($dhaka));
        $this->assertFalse($hours->kioskIsOpen($auckland));

        Date::setTestNow();
    }

    public function test_an_overnight_kiosk_window_wraps_past_midnight(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $this->actingAsTenant($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Asia/Dhaka']);

        // Wednesday 18:00 to Thursday 02:00, local time.
        StoreHour::factory()->forDay(3)->create([
            'store_id' => $store->id,
            'tenant_id' => $tenant->id,
            'opens_at' => '18:00:00',
            'closes_at' => '02:00:00',
        ]);

        $hours = app(StoreHoursService::class);

        // Thursday 01:00 Dhaka = Wednesday 19:00 UTC. The window belongs to
        // Wednesday's row, so a naive "look at today only" check would call
        // this closed.
        Date::setTestNow(CarbonImmutable::parse('2026-08-26 19:00:00', 'UTC'));
        $this->assertTrue($hours->kioskIsOpen($store));

        // Thursday 03:00 Dhaka = Wednesday 21:00 UTC — past closing.
        Date::setTestNow(CarbonImmutable::parse('2026-08-26 21:00:00', 'UTC'));
        $this->assertFalse($hours->kioskIsOpen($store));

        Date::setTestNow();
    }

    public function test_kiosk_hours_fall_back_to_the_shops_own_trading_hours(): void
    {
        // 07:00 UTC = 13:00 Dhaka, inside 09:00-21:00.
        Date::setTestNow(CarbonImmutable::parse('2026-08-26 07:00:00', 'UTC'));

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $this->actingAsTenant($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Asia/Dhaka']);
        StoreHour::factory()->forDay(3)->create([
            'store_id' => $store->id,
            'tenant_id' => $tenant->id,
            'opens_at' => '09:00:00',
            'closes_at' => '21:00:00',
            'kiosk_opens_at' => null,
            'kiosk_closes_at' => null,
        ]);

        $this->assertTrue(app(StoreHoursService::class)->kioskIsOpen($store));

        Date::setTestNow();
    }

    public function test_availability_reports_when_the_kiosk_next_opens(): void
    {
        // Wednesday 02:00 UTC = 08:00 Dhaka, an hour before opening.
        Date::setTestNow(CarbonImmutable::parse('2026-08-26 02:00:00', 'UTC'));

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $this->actingAsTenant($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Asia/Dhaka']);
        StoreHour::factory()->forDay(3)->create([
            'store_id' => $store->id,
            'tenant_id' => $tenant->id,
            'opens_at' => '09:00:00',
            'closes_at' => '21:00:00',
        ]);

        $availability = app(StoreHoursService::class)->availability($store);

        $this->assertFalse($availability['is_open']);
        $this->assertNotNull($availability['next_opens_at']);
        $this->assertSame(
            '2026-08-26T09:00:00+06:00',
            CarbonImmutable::parse($availability['next_opens_at'])->setTimezone('Asia/Dhaka')->toIso8601String(),
        );

        Date::setTestNow();
    }

    public function test_a_staff_member_can_read_but_not_change_hours(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $staff->assignRole('staff');

        $this->withHeaders($this->bearer($staff))
            ->getJson("/api/v1/stores/{$store->id}/hours")
            ->assertForbidden();

        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        $manager->assignRole('manager');

        $this->withHeaders($this->bearer($manager))
            ->putJson("/api/v1/stores/{$store->id}/hours", [
                'days' => [['day_of_week' => 1, 'is_closed' => true]],
            ])
            ->assertOk();
    }
}
