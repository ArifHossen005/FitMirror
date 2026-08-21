<?php

namespace Database\Factories;

use App\Enums\KioskDeviceStatus;
use App\Models\KioskDevice;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KioskDevice>
 */
class KioskDeviceFactory extends Factory
{
    protected $model = KioskDevice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => fake()->randomElement(['Front Desk', 'Fitting Room A', 'Entrance', 'Window Display']),
            'status' => KioskDeviceStatus::Pending,
            'settings' => [],
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (KioskDevice $device) {
            if ($device->tenant_id === null && $device->store_id !== null) {
                $store = Store::withoutTenantScope()->find($device->store_id);
                $device->tenant_id = $store?->tenant_id;
            }
        });
    }

    /**
     * A device with a live pairing code, ready to be claimed.
     */
    public function awaitingPairing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KioskDeviceStatus::Pending,
            'pairing_code' => KioskDevice::generatePairingCode(),
            'pairing_code_expires_at' => now()->addMinutes(KioskDevice::PAIRING_CODE_TTL_MINUTES),
        ]);
    }

    /**
     * A paired device. The raw token is not recoverable from the model, so
     * tests that need to make authenticated kiosk requests should use
     * pairedWithToken() instead and keep the returned token themselves.
     */
    public function paired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KioskDeviceStatus::Paired,
            'device_token_hash' => KioskDevice::hashDeviceToken(fake()->unique()->sha256()),
            'device_fingerprint' => fake()->uuid(),
            'paired_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Pairs the device with a caller-supplied raw token, so a test can hold
     * the plaintext it needs for an Authorization header while the row
     * still stores only the hash.
     */
    public function pairedWithToken(string $rawToken): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KioskDeviceStatus::Paired,
            'device_token_hash' => KioskDevice::hashDeviceToken($rawToken),
            'device_fingerprint' => fake()->uuid(),
            'paired_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    public function status(KioskDeviceStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
