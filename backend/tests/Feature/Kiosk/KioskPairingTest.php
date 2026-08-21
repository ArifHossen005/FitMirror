<?php

namespace Tests\Feature\Kiosk;

use App\Enums\KioskDeviceStatus;
use App\Enums\StoreStatus;
use App\Models\KioskDevice;
use App\Models\Store;
use App\Models\StoreHour;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/**
 * The kiosk lifecycle end to end: register → pairing code → claim →
 * authenticated calls → active-hours enforcement → unpair.
 *
 * Every kiosk request here goes through App\Http\Middleware\
 * AuthenticateKioskDevice rather than Sanctum, so these also cover that
 * middleware's own tenant resolution — a kiosk request carries no user and
 * no X-Tenant header, so the tenant can only come from the device itself.
 */
class KioskPairingTest extends TestCase
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
     * @return array<string, string>
     */
    private function deviceBearer(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    public function test_registering_a_kiosk_returns_a_pairing_code(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/stores/{$store->id}/kiosk-devices", ['name' => 'Front Desk']);

        $response->assertCreated();
        $response->assertJsonPath('data.status', KioskDeviceStatus::Pending->value);
        $code = $response->json('data.pairing_code');

        $this->assertIsString($code);
        $this->assertSame(KioskDevice::PAIRING_CODE_LENGTH, strlen($code));
        // The alphabet excludes I, O, 0 and 1 — the characters most often
        // misread off a screen.
        $this->assertMatchesRegularExpression('/^[' . KioskDevice::PAIRING_CODE_ALPHABET . ']+$/', $code);
    }

    public function test_the_device_list_never_exposes_a_live_pairing_code(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        KioskDevice::factory()->awaitingPairing()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
        ]);

        $response = $this->withHeaders($this->bearer($owner))
            ->getJson("/api/v1/stores/{$store->id}/kiosk-devices");

        $response->assertOk();
        $response->assertJsonPath('data.0.has_pending_code', true);
        $this->assertArrayNotHasKey('pairing_code', $response->json('data.0'));
    }

    public function test_a_device_claims_its_token_with_a_valid_pairing_code(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $device = KioskDevice::factory()->awaitingPairing()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
        ]);

        $response = $this->postJson('/api/v1/kiosk/claim', [
            'pairing_code' => $device->pairing_code,
            'device_fingerprint' => 'tablet-abc-123',
            'app_version' => '1.0.0',
        ]);

        $response->assertCreated();
        $token = $response->json('data.device_token');
        $this->assertIsString($token);

        $device->refresh();
        $this->assertSame(KioskDeviceStatus::Paired, $device->status);
        // Only the hash is ever stored — the raw token is returned once.
        $this->assertSame(KioskDevice::hashDeviceToken($token), $device->device_token_hash);
        $this->assertNull($device->pairing_code);
        $this->assertSame('tablet-abc-123', $device->device_fingerprint);
    }

    public function test_a_pairing_code_is_single_use(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $device = KioskDevice::factory()->awaitingPairing()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
        ]);
        $code = $device->pairing_code;

        $this->postJson('/api/v1/kiosk/claim', [
            'pairing_code' => $code,
            'device_fingerprint' => 'first-device',
        ])->assertCreated();

        $this->postJson('/api/v1/kiosk/claim', [
            'pairing_code' => $code,
            'device_fingerprint' => 'second-device',
        ])->assertStatus(422)->assertJsonValidationErrors('pairing_code');
    }

    public function test_an_expired_pairing_code_is_refused(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $device = KioskDevice::factory()->awaitingPairing()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
        ]);
        $device->forceFill(['pairing_code_expires_at' => now()->subMinute()])->save();

        $this->postJson('/api/v1/kiosk/claim', [
            'pairing_code' => $device->pairing_code,
            'device_fingerprint' => 'late-device',
        ])->assertStatus(422);
    }

    public function test_an_unknown_pairing_code_gives_the_same_answer_as_an_expired_one(): void
    {
        // Deliberately indistinguishable — an anonymous caller must not be
        // able to use this endpoint to tell live codes from dead ones.
        $this->postJson('/api/v1/kiosk/claim', [
            'pairing_code' => 'ZZZZZZZZ',
            'device_fingerprint' => 'nobody',
        ])->assertStatus(422)->assertJsonValidationErrors('pairing_code');
    }

    public function test_a_paired_device_can_heartbeat_and_the_dashboard_sees_it_online(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $token = 'raw-device-token-for-heartbeat';
        $device = KioskDevice::factory()->pairedWithToken($token)->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
        ]);
        $device->forceFill(['last_seen_at' => now()->subHour()])->save();

        $response = $this->withHeaders($this->deviceBearer($token))
            ->postJson('/api/v1/kiosk/heartbeat', [
                'app_version' => '1.2.0',
                'health' => ['camera_ok' => true, 'battery_percent' => 84],
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.next_heartbeat_in_seconds', KioskDevice::HEARTBEAT_INTERVAL_SECONDS);

        $device->refresh();
        $this->assertTrue($device->isOnline());
        $this->assertSame('1.2.0', $device->app_version);
        $this->assertSame(84, $device->health['battery_percent']);

        $this->withHeaders($this->bearer($owner))
            ->getJson("/api/v1/kiosk-devices/{$device->id}")
            ->assertOk()
            ->assertJsonPath('data.is_online', true);
    }

    public function test_an_unpaired_or_bogus_device_token_is_rejected(): void
    {
        $this->withHeaders($this->deviceBearer('not-a-real-token'))
            ->getJson('/api/v1/kiosk/config')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'kiosk_unauthenticated');

        $this->getJson('/api/v1/kiosk/config')->assertUnauthorized();
    }

    public function test_a_suspended_device_cannot_authenticate_but_keeps_its_token(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $token = 'raw-device-token-for-suspension';
        $device = KioskDevice::factory()->pairedWithToken($token)->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
        ]);

        $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/kiosk-devices/{$device->id}/suspend")
            ->assertOk();

        $this->withHeaders($this->deviceBearer($token))
            ->getJson('/api/v1/kiosk/config')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'kiosk_device_suspended');

        // Reactivating restores service without re-pairing the hardware.
        $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/kiosk-devices/{$device->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.status', KioskDeviceStatus::Paired->value);

        $this->withHeaders($this->deviceBearer($token))
            ->getJson('/api/v1/kiosk/config')
            ->assertOk();
    }

    public function test_remote_unpair_revokes_the_device_token_and_issues_a_new_code(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $token = 'raw-device-token-for-unpair';
        $device = KioskDevice::factory()->pairedWithToken($token)->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
        ]);

        $response = $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/kiosk-devices/{$device->id}/unpair");

        $response->assertOk();
        $response->assertJsonPath('data.status', KioskDeviceStatus::Pending->value);
        $this->assertIsString($response->json('data.pairing_code'));

        $this->withHeaders($this->deviceBearer($token))
            ->getJson('/api/v1/kiosk/config')
            ->assertUnauthorized();
    }

    public function test_a_kiosk_request_resolves_its_tenant_from_the_device_alone(): void
    {
        // No X-Tenant header, no user — the only thing identifying the
        // tenant is the device token itself.
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $store = Store::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Sylhet Showroom']);
        $token = 'raw-device-token-for-tenant-resolution';
        KioskDevice::factory()->pairedWithToken($token)->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
        ]);

        $this->withHeaders($this->deviceBearer($token))
            ->getJson('/api/v1/kiosk/config')
            ->assertOk()
            ->assertJsonPath('data.store.name', 'Sylhet Showroom')
            ->assertJsonPath('data.settings.language', 'bn');
    }

    public function test_display_settings_are_merged_not_replaced(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $device = KioskDevice::factory()->create(['tenant_id' => $tenant->id, 'store_id' => $store->id]);

        $this->withHeaders($this->bearer($owner))
            ->putJson("/api/v1/kiosk-devices/{$device->id}/settings", ['language' => 'en'])
            ->assertOk();

        $response = $this->withHeaders($this->bearer($owner))
            ->putJson("/api/v1/kiosk-devices/{$device->id}/settings", ['theme' => 'dark']);

        $response->assertOk();
        $response->assertJsonPath('data.settings.language', 'en');
        $response->assertJsonPath('data.settings.theme', 'dark');
        // Untouched keys still resolve to their defaults.
        $response->assertJsonPath('data.settings.idle_timeout_seconds', 120);
    }

    public function test_an_out_of_range_idle_timeout_is_rejected(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $device = KioskDevice::factory()->create(['tenant_id' => $tenant->id, 'store_id' => $store->id]);

        $this->withHeaders($this->bearer($owner))
            ->putJson("/api/v1/kiosk-devices/{$device->id}/settings", ['idle_timeout_seconds' => 99999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('idle_timeout_seconds');
    }

    public function test_session_authorization_is_blocked_outside_kiosk_active_hours(): void
    {
        // Wednesday 2026-08-26, 22:00 Dhaka time — after the kiosk window.
        Date::setTestNow(CarbonImmutable::parse('2026-08-26 16:00:00', 'UTC'));

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $store = Store::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Asia/Dhaka']);
        StoreHour::factory()
            ->forDay(3)
            ->kioskWindow('10:00:00', '20:00:00')
            ->create(['store_id' => $store->id, 'tenant_id' => $tenant->id]);

        $token = 'raw-device-token-for-hours';
        KioskDevice::factory()->pairedWithToken($token)->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
        ]);

        $response = $this->withHeaders($this->deviceBearer($token))
            ->postJson('/api/v1/kiosk/sessions/authorize');

        $response->assertForbidden();
        $response->assertJsonPath('error_code', 'kiosk_outside_active_hours');
        $response->assertJsonPath('errors.is_open', false);

        // The unguarded endpoints still answer, so the kiosk can render a
        // "we are closed" screen and keep heartbeating.
        $this->withHeaders($this->deviceBearer($token))
            ->getJson('/api/v1/kiosk/availability')
            ->assertOk()
            ->assertJsonPath('data.is_open', false);

        $this->withHeaders($this->deviceBearer($token))
            ->postJson('/api/v1/kiosk/heartbeat')
            ->assertOk();

        Date::setTestNow();
    }

    public function test_session_authorization_succeeds_inside_kiosk_active_hours(): void
    {
        // Wednesday 2026-08-26, 18:00 Dhaka time — inside the window.
        Date::setTestNow(CarbonImmutable::parse('2026-08-26 12:00:00', 'UTC'));

        $tenant = Tenant::factory()->onPlan('pro')->create();
        $store = Store::factory()->create(['tenant_id' => $tenant->id, 'timezone' => 'Asia/Dhaka']);
        StoreHour::factory()
            ->forDay(3)
            ->kioskWindow('10:00:00', '20:00:00')
            ->create(['store_id' => $store->id, 'tenant_id' => $tenant->id]);

        $token = 'raw-device-token-for-open-hours';
        KioskDevice::factory()->pairedWithToken($token)->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
        ]);

        $this->withHeaders($this->deviceBearer($token))
            ->postJson('/api/v1/kiosk/sessions/authorize')
            ->assertOk()
            ->assertJsonPath('data.authorized', true);

        Date::setTestNow();
    }

    public function test_a_non_operational_branch_blocks_the_kiosk_regardless_of_hours(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $store = Store::factory()->status(StoreStatus::Inactive)->create(['tenant_id' => $tenant->id]);
        $token = 'raw-device-token-for-inactive-store';
        KioskDevice::factory()->pairedWithToken($token)->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
        ]);

        $this->withHeaders($this->deviceBearer($token))
            ->postJson('/api/v1/kiosk/sessions/authorize')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'kiosk_store_not_operational');
    }

    public function test_a_tenant_cannot_manage_another_tenants_kiosk_device(): void
    {
        $tenant = Tenant::factory()->onPlan('pro')->create();
        $owner = $this->ownerFor($tenant);
        $foreignTenant = Tenant::factory()->onPlan('pro')->create();
        $foreignStore = Store::factory()->create(['tenant_id' => $foreignTenant->id]);
        $foreignDevice = KioskDevice::factory()->create([
            'tenant_id' => $foreignTenant->id,
            'store_id' => $foreignStore->id,
        ]);

        $this->withHeaders($this->bearer($owner))
            ->postJson("/api/v1/kiosk-devices/{$foreignDevice->id}/unpair")
            ->assertNotFound();
    }
}
