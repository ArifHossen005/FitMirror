<?php

namespace App\Http\Middleware;

use App\Models\KioskDevice;
use App\Models\Tenant;
use App\Support\ApiResponse;
use App\Support\KioskContext;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a kiosk by its long-lived device token, and resolves the
 * tenant from that device.
 *
 * Deliberately not Sanctum. A kiosk is not a user: nobody signs in at it,
 * it has no email, no password, no 2FA, and its credential must survive
 * reboots and staff turnover unattended. Modelling it as a Sanctum
 * tokenable would mean either a second auth guard whose provider resolves
 * a non-User model, or widening PersonalAccessToken's morph map for a
 * principal that shares none of a user's semantics — both more machinery
 * than a device token lookup needs, and both harder to reason about than
 * the twelve lines below.
 *
 * The lookup uses KioskDevice::withoutTenantScope(). Like login and kiosk
 * pairing, authentication has to discover which tenant the caller belongs
 * to *before* a tenant context can exist — the same structural reason
 * behind every bypass catalogued in Decision D-13. Once the device is
 * resolved, its tenant is pushed into TenantContext so every subsequent
 * BelongsToTenant query in the request is scoped normally.
 */
class AuthenticateKioskDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->tokenFrom($request);

        if ($token === null) {
            return $this->unauthenticated('A kiosk device token is required.');
        }

        $device = KioskDevice::withoutTenantScope()
            ->where('device_token_hash', KioskDevice::hashDeviceToken($token))
            ->first();

        if (!$device instanceof KioskDevice) {
            return $this->unauthenticated('This kiosk is not paired. Pair it again from your dashboard.');
        }

        if (!$device->status->canAuthenticate()) {
            return ApiResponse::error(
                'This kiosk has been suspended. Contact your shop owner.',
                Response::HTTP_FORBIDDEN,
                errorCode: 'kiosk_device_suspended',
            );
        }

        $tenant = Tenant::query()->find($device->tenant_id);

        if (!$tenant instanceof Tenant) {
            return $this->unauthenticated('The account this kiosk belongs to is no longer available.');
        }

        // The context must be established *before* the branch is loaded.
        // Store carries TenantScope, which fails closed (Decision D-13), so
        // reading $device->store any earlier returns null for every device —
        // the branch would look deleted and every kiosk request would 401.
        // Resolving the tenant from the device's own tenant_id (Tenant is
        // the one model without TenantScope) breaks that chicken-and-egg.
        app(TenantContext::class)->set($tenant);
        app(KioskContext::class)->set($device);
        $request->attributes->set('tenant_id', $device->tenant_id);

        $store = $device->store;

        // The relation is withTrashed() (see KioskDevice::store()), so a
        // removed branch resolves to a soft-deleted row rather than null.
        // StoreService::delete() already revokes device tokens when a branch
        // goes, making this the belt-and-braces path for a row deleted by
        // hand or restored without its devices being re-paired.
        if ($store === null || $store->trashed()) {
            app(TenantContext::class)->forget();
            app(KioskContext::class)->forget();

            return $this->unauthenticated('This kiosk is no longer assigned to a branch.');
        }

        return $next($request);
    }

    /**
     * Accepts the token as a normal bearer credential, or via X-Kiosk-Token
     * for kiosk builds running inside a webview that reserves the
     * Authorization header for its own host app.
     */
    private function tokenFrom(Request $request): ?string
    {
        $token = $request->bearerToken() ?: $request->header('X-Kiosk-Token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function unauthenticated(string $message): Response
    {
        return ApiResponse::error(
            $message,
            Response::HTTP_UNAUTHORIZED,
            errorCode: 'kiosk_unauthenticated',
        );
    }
}
