<?php

namespace App\Http\Controllers\Api\V1\Kiosk;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Kiosk\RegisterKioskDeviceRequest;
use App\Http\Requests\Kiosk\UpdateKioskSettingsRequest;
use App\Models\KioskDevice;
use App\Models\Store;
use App\Services\Kiosk\KioskPairingService;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard-side kiosk management: register a device against a branch,
 * hand out and refresh its pairing code, see whether it is online, change
 * its display settings, suspend it, unpair it, delete it.
 *
 * The kiosk's *own* endpoints (claim, heartbeat, config) live in
 * KioskSessionController and authenticate with a device token instead —
 * nothing here is reachable by a kiosk, and nothing there is reachable by
 * a dashboard user.
 *
 * The raw pairing code is returned only by the two endpoints that mint one
 * (store, pairingCode). present() never includes it, so the device list
 * cannot become a way to read every live code at once.
 */
class KioskDeviceController extends BaseApiController
{
    public function __construct(private readonly KioskPairingService $pairing) {}

    public function index(Request $request, Store $store): JsonResponse
    {
        $this->authorize('viewKiosks', $store);

        $devices = $store->kioskDevices()
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return $this->paginated($devices->through(fn (KioskDevice $device) => $this->present($device)));
    }

    public function store(RegisterKioskDeviceRequest $request, Store $store): JsonResponse
    {
        $this->authorize('manageKiosks', $store);

        $result = $this->pairing->register($store, $request->validated());

        return $this->created(
            $this->presentWithCode($result['device'], $result['pairing_code'], $result['expires_at']),
            'Kiosk registered. Enter the pairing code on the device to finish setup.',
        );
    }

    public function show(KioskDevice $device): JsonResponse
    {
        $this->authorize('viewKiosks', $device->store);

        return $this->success($this->present($device));
    }

    /**
     * Issues a fresh pairing code, invalidating any previous one. Used both
     * for a device that was never paired and for one whose code expired
     * before a staff member got to it.
     */
    public function pairingCode(KioskDevice $device): JsonResponse
    {
        $this->authorize('manageKiosks', $device->store);

        $result = $this->pairing->issuePairingCode($device);

        return $this->success(
            $this->presentWithCode($result['device'], $result['pairing_code'], $result['expires_at']),
            'New pairing code generated.',
        );
    }

    /**
     * Remote unpair — revokes the device token and returns a fresh pairing
     * code, so the same hardware can be re-paired without losing its name,
     * settings or history.
     */
    public function unpair(KioskDevice $device): JsonResponse
    {
        $this->authorize('manageKiosks', $device->store);

        $result = $this->pairing->unpair($device);

        return $this->success(
            $this->presentWithCode($result['device'], $result['pairing_code'], $result['expires_at']),
            'Kiosk unpaired. It will need the new pairing code to reconnect.',
        );
    }

    public function suspend(KioskDevice $device): JsonResponse
    {
        $this->authorize('manageKiosks', $device->store);

        return $this->success($this->present($this->pairing->suspend($device)), 'Kiosk suspended.');
    }

    public function reactivate(KioskDevice $device): JsonResponse
    {
        $this->authorize('manageKiosks', $device->store);

        return $this->success($this->present($this->pairing->reactivate($device)), 'Kiosk reactivated.');
    }

    public function settings(KioskDevice $device): JsonResponse
    {
        $this->authorize('viewKiosks', $device->store);

        return $this->success([
            'device_id' => $device->id,
            'settings' => $device->settings(),
        ]);
    }

    public function updateSettings(UpdateKioskSettingsRequest $request, KioskDevice $device): JsonResponse
    {
        $this->authorize('manageKiosks', $device->store);

        $updated = $this->pairing->updateSettings($device, $request->validated());

        return $this->success([
            'device_id' => $updated->id,
            'settings' => $updated->settings(),
        ], 'Display settings updated successfully.');
    }

    public function destroy(KioskDevice $device): JsonResponse
    {
        $this->authorize('manageKiosks', $device->store);

        $this->pairing->delete($device);

        return $this->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(KioskDevice $device): array
    {
        return [
            'id' => $device->id,
            'store_id' => $device->store_id,
            'name' => $device->name,
            'status' => $device->status->value,
            'status_label' => $device->status->label(),
            'is_online' => $device->isOnline(),
            'device_fingerprint' => $device->device_fingerprint,
            'app_version' => $device->app_version,
            'paired_at' => $device->paired_at?->toIso8601String(),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'last_seen_ip' => $device->last_seen_ip,
            'health' => $device->health,
            'settings' => $device->settings(),
            'has_pending_code' => $device->hasUsablePairingCode(),
            'pairing_code_expires_at' => $device->pairing_code_expires_at?->toIso8601String(),
            'created_at' => $device->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentWithCode(KioskDevice $device, string $code, DateTimeInterface $expiresAt): array
    {
        // array_merge, not `+`: the union operator keeps the left operand's
        // value on a duplicate key, which would silently return the stale
        // pairing_code_expires_at that present() already put there.
        return array_merge($this->present($device), [
            'pairing_code' => $code,
            'pairing_code_expires_at' => $expiresAt->format(DateTimeInterface::ATOM),
        ]);
    }
}
