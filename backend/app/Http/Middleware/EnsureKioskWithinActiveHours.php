<?php

namespace App\Http\Middleware;

use App\Services\Store\StoreHoursService;
use App\Support\ApiResponse;
use App\Support\KioskContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a kiosk from starting work outside its branch's configured
 * kiosk-active window. Runs after `kiosk.auth`, which is what puts the
 * device in KioskContext.
 *
 * Attached only to the session-authorisation route, never to the heartbeat
 * or config routes: a closed kiosk must still be able to report in and
 * fetch its display settings, otherwise the dashboard would show every
 * out-of-hours device as offline and the kiosk would have nothing to
 * render its "we are closed" screen from. The unguarded availability
 * endpoint exists for exactly that screen.
 *
 * A branch whose status is not Active blocks here too — administratively
 * shut is a stronger statement than "outside opening hours", and both
 * should stop a session for the same reason.
 */
class EnsureKioskWithinActiveHours
{
    public function __construct(private readonly StoreHoursService $hours) {}

    public function handle(Request $request, Closure $next): Response
    {
        $device = app(KioskContext::class)->deviceOrFail();
        $store = $device->store;

        if ($store === null || !$store->status->isOperational()) {
            return ApiResponse::error(
                'This branch is not currently operating.',
                Response::HTTP_FORBIDDEN,
                errorCode: 'kiosk_store_not_operational',
            );
        }

        if (!$this->hours->kioskIsOpen($store)) {
            return ApiResponse::error(
                'The kiosk is outside its active hours for this branch.',
                Response::HTTP_FORBIDDEN,
                errors: $this->hours->availability($store),
                errorCode: 'kiosk_outside_active_hours',
            );
        }

        return $next($request);
    }
}
