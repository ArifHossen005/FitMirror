<?php

namespace App\Services\Kiosk;

use App\Enums\KioskDeviceStatus;
use App\Models\KioskDevice;
use App\Models\Store;
use App\Services\BaseService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The kiosk device lifecycle: register (dashboard) → pair (dashboard hands
 * out a short-lived code) → claim (the device redeems that code for a
 * long-lived token) → heartbeat → unpair.
 *
 * claim() is the only method here reachable without any FitMirror
 * credentials — the kiosk has none until it succeeds. It therefore looks
 * the code up with KioskDevice::withoutTenantScope(), which is a
 * deliberate, audited addition to the bypass list catalogued in Decision
 * D-13: like login, pairing has to discover *which* tenant it belongs to
 * before a tenant context can possibly exist. Everything after claim runs
 * inside a resolved tenant context set by AuthenticateKioskDevice.
 */
class KioskPairingService extends BaseService
{
    /**
     * Kiosks are deliberately not metered against the plan's `branches`
     * limit — a kiosk is not a branch, and the real constraint on how many
     * a shop runs is floor space, not billing. This flat per-branch ceiling
     * exists only so a scripting mistake cannot create rows without bound.
     */
    public const MAX_DEVICES_PER_STORE = 20;

    /**
     * Registers a new kiosk against a branch and issues its first pairing
     * code.
     *
     * @param array{name: string, settings?: array<string, mixed>} $data
     * @return array{device: KioskDevice, pairing_code: string, expires_at: Carbon}
     */
    public function register(Store $store, array $data): array
    {
        $existing = KioskDevice::query()->where('store_id', $store->id)->count();

        if ($existing >= self::MAX_DEVICES_PER_STORE) {
            throw ValidationException::withMessages([
                'store_id' => ['This branch already has the maximum number of kiosk devices.'],
            ]);
        }

        return $this->transaction(function () use ($store, $data) {
            $device = KioskDevice::query()->create([
                'tenant_id' => $store->tenant_id,
                'store_id' => $store->id,
                'name' => $data['name'],
                'status' => KioskDeviceStatus::Pending->value,
                'settings' => $this->sanitiseSettings($data['settings'] ?? []),
            ]);

            return $this->issuePairingCode($device);
        });
    }

    /**
     * Issues (or re-issues) a pairing code. Re-issuing invalidates the
     * previous code by overwriting it, so a code left on a screen in the
     * back office stops working the moment a new one is generated.
     *
     * @return array{device: KioskDevice, pairing_code: string, expires_at: Carbon}
     */
    public function issuePairingCode(KioskDevice $device): array
    {
        if ($device->status === KioskDeviceStatus::Suspended) {
            throw ValidationException::withMessages([
                'device' => ['Reactivate this device before pairing it.'],
            ]);
        }

        $code = KioskDevice::generatePairingCode();
        $expiresAt = now()->addMinutes(KioskDevice::PAIRING_CODE_TTL_MINUTES);

        $device->forceFill([
            'pairing_code' => $code,
            'pairing_code_expires_at' => $expiresAt,
        ])->save();

        return ['device' => $device, 'pairing_code' => $code, 'expires_at' => $expiresAt];
    }

    /**
     * Redeems a pairing code for a long-lived device token. Called by the
     * kiosk itself, unauthenticated.
     *
     * The raw token is returned exactly once here and never recoverable
     * afterwards — only its sha256 is stored (KioskDevice's own docblock).
     *
     * @param array{pairing_code: string, device_fingerprint: string, app_version?: string} $data
     * @return array{device: KioskDevice, token: string}
     */
    public function claim(array $data): array
    {
        // See this class's docblock — this is the one query in the kiosk
        // surface that legitimately runs outside a tenant context, for the
        // same structural reason LoginService's email lookup does.
        $device = KioskDevice::withoutTenantScope()
            ->where('pairing_code', strtoupper(trim($data['pairing_code'])))
            ->first();

        if (!$device instanceof KioskDevice || !$device->hasUsablePairingCode()) {
            throw ValidationException::withMessages([
                'pairing_code' => ['This pairing code is invalid or has expired.'],
            ]);
        }

        if ($device->status === KioskDeviceStatus::Suspended) {
            throw ValidationException::withMessages([
                'pairing_code' => ['This device has been suspended. Contact your shop owner.'],
            ]);
        }

        return $this->transaction(function () use ($device, $data) {
            ['token' => $token, 'hash' => $hash] = KioskDevice::generateDeviceToken();

            $device->forceFill([
                'device_token_hash' => $hash,
                'device_fingerprint' => $data['device_fingerprint'],
                'app_version' => $data['app_version'] ?? null,
                'status' => KioskDeviceStatus::Paired->value,
                'paired_at' => now(),
                'last_seen_at' => now(),
                // Single-use: consumed the moment it succeeds, so a code
                // overheard or photographed cannot pair a second device.
                'pairing_code' => null,
                'pairing_code_expires_at' => null,
            ])->save();

            return ['device' => $device, 'token' => $token];
        });
    }

    /**
     * Records a heartbeat from a paired device.
     *
     * @param array{app_version?: string, health?: array<string, mixed>, ip?: string} $data
     */
    public function heartbeat(KioskDevice $device, array $data): KioskDevice
    {
        $device->forceFill([
            'last_seen_at' => now(),
            'last_seen_ip' => $data['ip'] ?? null,
            'app_version' => $data['app_version'] ?? $device->app_version,
            'health' => $data['health'] ?? $device->health,
        ])->save();

        return $device;
    }

    /**
     * Revokes the device token and returns the device to Pending with a
     * fresh pairing code, so the same hardware can be re-paired without the
     * tenant having to delete and re-create it (losing its name, settings
     * and history).
     *
     * @return array{device: KioskDevice, pairing_code: string, expires_at: Carbon}
     */
    public function unpair(KioskDevice $device): array
    {
        return $this->transaction(function () use ($device) {
            $device->forceFill([
                'device_token_hash' => null,
                'device_fingerprint' => null,
                'paired_at' => null,
                'status' => KioskDeviceStatus::Pending->value,
            ])->save();

            return $this->issuePairingCode($device);
        });
    }

    public function suspend(KioskDevice $device): KioskDevice
    {
        // The token hash is deliberately kept: KioskDeviceStatus::Suspended
        // blocks authentication on its own (canAuthenticate()), so lifting
        // a suspension is instant and does not send someone back to the
        // shop floor to re-pair the hardware.
        $device->forceFill([
            'status' => KioskDeviceStatus::Suspended->value,
            'pairing_code' => null,
            'pairing_code_expires_at' => null,
        ])->save();

        return $device;
    }

    public function reactivate(KioskDevice $device): KioskDevice
    {
        if ($device->status !== KioskDeviceStatus::Suspended) {
            throw ValidationException::withMessages([
                'device' => ['Only a suspended device can be reactivated.'],
            ]);
        }

        $device->forceFill([
            'status' => $device->device_token_hash
                ? KioskDeviceStatus::Paired->value
                : KioskDeviceStatus::Pending->value,
        ])->save();

        return $device;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function updateSettings(KioskDevice $device, array $settings): KioskDevice
    {
        // Merged over what is already stored, not replaced, so a client
        // sending only `theme` does not silently reset every other setting
        // to its default.
        $device->settings = $this->sanitiseSettings(array_merge($device->settings ?? [], $settings));
        $device->save();

        return $device;
    }

    public function delete(KioskDevice $device): void
    {
        $device->delete();
    }

    /**
     * Keeps only keys the kiosk app actually understands, clamped to their
     * valid ranges. An unknown key is dropped rather than stored, so the
     * settings column cannot accumulate values no released kiosk build
     * reads.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function sanitiseSettings(array $settings): array
    {
        $clean = [];

        foreach ($settings as $key => $value) {
            if (!array_key_exists($key, KioskDevice::DEFAULT_SETTINGS)) {
                continue;
            }

            $clean[$key] = match ($key) {
                'idle_timeout_seconds' => max(
                    KioskDevice::MIN_IDLE_TIMEOUT_SECONDS,
                    min(KioskDevice::MAX_IDLE_TIMEOUT_SECONDS, (int) $value),
                ),
                'screensaver_playlist' => array_values(array_filter(
                    is_array($value) ? $value : [],
                    static fn ($item) => is_string($item) && trim($item) !== '',
                )),
                'show_branding', 'attract_loop_enabled' => (bool) $value,
                default => $value,
            };
        }

        return $clean;
    }
}
