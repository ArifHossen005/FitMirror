<?php

namespace Tests\Feature\Store;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff rostering. The interesting cases are the ones a field-level
 * validation rule cannot express: double-booking one person, an overnight
 * shift that really does collide with the next morning, and rostering
 * someone who belongs to a different shop.
 */
class ShiftTest extends TestCase
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

    public function test_a_manager_can_roster_a_staff_member(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $staff->assignRole('staff');

        $response = $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/stores/{$store->id}/shifts", [
                'user_id' => $staff->id,
                'shift_date' => '2026-09-01',
                'starts_at' => '09:00',
                'ends_at' => '17:00',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.duration_minutes', 480);
        $response->assertJsonPath('data.is_overnight', false);
        $response->assertJsonPath('data.status', ShiftStatus::Scheduled->value);
    }

    public function test_an_overnight_shift_is_recognised_as_wrapping_past_midnight(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/stores/{$store->id}/shifts", [
                'user_id' => $staff->id,
                'shift_date' => '2026-09-01',
                'starts_at' => '22:00',
                'ends_at' => '06:00',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.is_overnight', true);
        $response->assertJsonPath('data.duration_minutes', 480);
    }

    public function test_a_staff_member_cannot_be_double_booked(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        Shift::factory()->on('2026-09-01', '09:00:00', '17:00:00')->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $staff->id,
        ]);

        $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/stores/{$store->id}/shifts", [
                'user_id' => $staff->id,
                'shift_date' => '2026-09-01',
                'starts_at' => '16:00',
                'ends_at' => '20:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_an_overnight_shift_collides_with_the_next_mornings_shift(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        // Runs until 06:00 on 2026-09-02.
        Shift::factory()->on('2026-09-01', '22:00:00', '06:00:00')->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $staff->id,
        ]);

        // A naive same-date comparison would see two different dates and
        // allow this; the instants genuinely overlap between 05:00 and 06:00.
        $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/stores/{$store->id}/shifts", [
                'user_id' => $staff->id,
                'shift_date' => '2026-09-02',
                'starts_at' => '05:00',
                'ends_at' => '13:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_a_cancelled_shift_does_not_block_a_replacement(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        Shift::factory()->on('2026-09-01', '09:00:00', '17:00:00')->cancelled()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'user_id' => $staff->id,
        ]);

        $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/stores/{$store->id}/shifts", [
                'user_id' => $staff->id,
                'shift_date' => '2026-09-01',
                'starts_at' => '09:00',
                'ends_at' => '17:00',
            ])
            ->assertCreated();
    }

    public function test_a_shift_longer_than_the_maximum_is_rejected(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/stores/{$store->id}/shifts", [
                'user_id' => $staff->id,
                'shift_date' => '2026-09-01',
                'starts_at' => '06:00',
                'ends_at' => '23:59',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ends_at');
    }

    public function test_a_staff_member_from_another_tenant_cannot_be_rostered(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $foreignStaff = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/stores/{$store->id}/shifts", [
                'user_id' => $foreignStaff->id,
                'shift_date' => '2026-09-01',
                'starts_at' => '09:00',
                'ends_at' => '17:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    public function test_the_schedule_listing_spans_branches_and_respects_the_range(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $storeA = Store::factory()->create(['tenant_id' => $tenant->id]);
        $storeB = Store::factory()->create(['tenant_id' => $tenant->id]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherStaff = User::factory()->create(['tenant_id' => $tenant->id]);

        Shift::factory()->on('2026-09-01')->create([
            'tenant_id' => $tenant->id, 'store_id' => $storeA->id, 'user_id' => $staff->id,
        ]);
        Shift::factory()->on('2026-09-02')->create([
            'tenant_id' => $tenant->id, 'store_id' => $storeB->id, 'user_id' => $otherStaff->id,
        ]);
        Shift::factory()->on('2026-09-20')->create([
            'tenant_id' => $tenant->id, 'store_id' => $storeA->id, 'user_id' => $staff->id,
        ]);

        $response = $this->withHeaders($this->bearer($owner))
            ->getJson('/api/v1/shifts/schedule?from=2026-09-01&to=2026-09-07');

        $response->assertOk();
        $response->assertJsonCount(2, 'data.shifts');

        $filtered = $this->withHeaders($this->bearer($owner))
            ->getJson("/api/v1/shifts/schedule?from=2026-09-01&to=2026-09-07&store_id={$storeA->id}");

        $filtered->assertOk();
        $filtered->assertJsonCount(1, 'data.shifts');
    }

    public function test_an_excessive_schedule_range_is_rejected(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);

        $this->withHeaders($this->bearer($owner))
            ->getJson('/api/v1/shifts/schedule?from=2020-01-01&to=2026-01-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }

    public function test_a_shift_can_be_moved_without_colliding_with_itself(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $shift = Shift::factory()->on('2026-09-01', '09:00:00', '17:00:00')->create([
            'tenant_id' => $tenant->id, 'store_id' => $store->id, 'user_id' => $staff->id,
        ]);

        $this->withHeaders($this->bearer($owner))
            ->patchJson("/api/v1/shifts/{$shift->id}", ['starts_at' => '10:00', 'ends_at' => '18:00'])
            ->assertOk()
            ->assertJsonPath('data.starts_at', '10:00');
    }

    public function test_cancelling_a_shift_keeps_it_on_the_schedule(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $shift = Shift::factory()->on('2026-09-01')->create([
            'tenant_id' => $tenant->id, 'store_id' => $store->id, 'user_id' => $staff->id,
        ]);

        $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/shifts/{$shift->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', ShiftStatus::Cancelled->value);

        $this->withHeaders($this->bearer($owner))
            ->getJson('/api/v1/shifts/schedule?from=2026-09-01&to=2026-09-01')
            ->assertOk()
            ->assertJsonCount(1, 'data.shifts');
    }

    public function test_a_plain_staff_account_cannot_roster_anyone(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $staff->assignRole('staff');

        $this->withHeaders($this->bearer($staff))
            ->postJson("/api/v1/stores/{$store->id}/shifts", [
                'user_id' => $staff->id,
                'shift_date' => '2026-09-01',
                'starts_at' => '09:00',
                'ends_at' => '17:00',
            ])
            ->assertForbidden();
    }

    public function test_shifts_are_isolated_between_tenants(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $foreignTenant = Tenant::factory()->onPlan('pro')->create();
        $foreignStore = Store::factory()->create(['tenant_id' => $foreignTenant->id]);
        $foreignStaff = User::factory()->create(['tenant_id' => $foreignTenant->id]);
        $foreignShift = Shift::factory()->on('2026-09-01')->create([
            'tenant_id' => $foreignTenant->id,
            'store_id' => $foreignStore->id,
            'user_id' => $foreignStaff->id,
        ]);

        $this->withHeaders($this->bearer($owner))
            ->getJson('/api/v1/shifts/schedule?from=2026-09-01&to=2026-09-07')
            ->assertOk()
            ->assertJsonCount(0, 'data.shifts');

        $this->withHeaders($this->bearer($owner))
            ->patchJson("/api/v1/shifts/{$foreignShift->id}", ['note' => 'nope'])
            ->assertNotFound();
    }
}
