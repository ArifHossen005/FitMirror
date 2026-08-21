<?php

namespace App\Http\Controllers\Api\V1\Kiosk;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Kiosk\ClaimKioskDeviceRequest;
use App\Http\Requests\Kiosk\KioskHeartbeatRequest;
use App\Models\KioskDevice;
use App\Services\Kiosk\KioskPairingService;
use App\Services\Store\StoreHoursService;
use App\Support\KioskContext;
use Illuminate\Http\JsonResponse;

/**
 * The kiosk device's own API surface. Authenticated by device token
 * (App\Http\Middleware\AuthenticateKioskDevice), never by a user session —
 * see that middleware's docblock for why this is not Sanctum.
 *
 * Only claim() is unauthenticated: it is how a device obtains its token in
 * the first place.
 *
 * availability() and config() are deliberately outside the active-hours
 * guard. A closed kiosk still has to fetch its branding and render a "we
 * are closed" screen, and still has to heartbeat so the dashboard does not
 * report every out-of-hours device as offline. authorizeSession() is the
 * one route the guard protects — it is the question "may I start serving a
 * customer right now", which Phase 6's try-on session creation will call
 * before opening a session.
 */
class KioskSessionController extends BaseApiController
{
    public function __construct(
        private readonly KioskPairingService $pairing,
        private readonly StoreHoursService $hours,
    ) {}

    /**
     * Unauthenticated. Redeems a pairing code for a long-lived device
     * token, returned exactly once here and never recoverable afterwards.
     */
    public function claim(ClaimKioskDeviceRequest $request): JsonResponse
    {
        $result = $this->pairing->claim($request->validated());

        return $this->created([
            'device_token' => $result['token'],
            'device' => $this->presentDevice($result['device']),
        ], 'Kiosk paired successfully.');
    }

    public function heartbeat(KioskHeartbeatRequest $request): JsonResponse
    {
        $device = app(KioskContext::class)->deviceOrFail();

        $payload = $request->validated();
        $payload['ip'] = $request->ip();

        $updated = $this->pairing->heartbeat($device, $payload);

        return $this->success([
            'acknowledged_at' => $updated->last_seen_at?->toIso8601String(),
            'next_heartbeat_in_seconds' => KioskDevice::HEARTBEAT_INTERVAL_SECONDS,
            // Settings ride back on every heartbeat so a change made in the
            // dashboard reaches an unattended kiosk within one interval,
            // with no polling endpoint of its own and no push channel.
            'settings' => $updated->settings(),
            'store_status' => $updated->store?->status->value,
        ]);
    }

    /**
     * Everything the kiosk needs to render itself: its own identity, its
     * branch's branding, its display settings, and whether it is currently
     * inside its active hours.
     */
    public function config(): JsonResponse
    {
        $device = app(KioskContext::class)->deviceOrFail();
        $store = $device->store;

        return $this->success([
            'device' => $this->presentDevice($device),
            'settings' => $device->settings(),
            'store' => $store === null ? null : [
                'id' => $store->id,
                'name' => $store->name,
                'code' => $store->code,
                'city' => $store->city,
                'area' => $store->area,
                'phone' => $store->phone,
                'logo_url' => $store->assetUrl($store->logo),
                'banner_url' => $store->assetUrl($store->banner),
                'socials' => $store->socials ?? [],
                'timezone' => $store->timezone,
                'status' => $store->status->value,
            ],
            'availability' => $store === null ? null : $this->hours->availability($store),
        ]);
    }

    /**
     * Whether the kiosk may serve a customer right now. Behind the
     * active-hours guard, so reaching the body at all means the answer is
     * yes — a refusal is rendered by the middleware with the branch's own
     * availability payload attached.
     */
    public function authorizeSession(): JsonResponse
    {
        $device = app(KioskContext::class)->deviceOrFail();
        $store = $device->store;

        return $this->success([
            'authorized' => true,
            'device_id' => $device->id,
            'store_id' => $device->store_id,
            'settings' => $device->settings(),
            'availability' => $store === null ? null : $this->hours->availability($store),
        ]);
    }

    /**
     * Unguarded companion to authorizeSession(), so a closed kiosk can show
     * a countdown to opening rather than an error.
     */
    public function availability(): JsonResponse
    {
        $device = app(KioskContext::class)->deviceOrFail();
        $store = $device->store;

        if ($store === null) {
            return $this->success(['is_open' => false, 'reason' => 'This kiosk is not assigned to a branch.']);
        }

        return $this->success($this->hours->availability($store));
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDevice(KioskDevice $device): array
    {
        return [
            'id' => $device->id,
            'name' => $device->name,
            'store_id' => $device->store_id,
            'status' => $device->status->value,
            'paired_at' => $device->paired_at?->toIso8601String(),
        ];
    }
}
