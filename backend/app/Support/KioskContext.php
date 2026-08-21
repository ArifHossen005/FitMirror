<?php

namespace App\Support;

use App\Models\KioskDevice;
use RuntimeException;

/**
 * "Which kiosk device is this request", the device-side counterpart to
 * TenantContext. Bound as a container singleton
 * (AppServiceProvider::register()) and set once per request by
 * AuthenticateKioskDevice.
 *
 * A singleton for the same reason TenantContext is, and with the same
 * caveat: a queue worker reuses one container across many jobs, so nothing
 * may assume this resets on its own. No queued job reads it today; any
 * that ever does must set and forget around its own handle(), the way
 * TenantAwareJob already does for the tenant.
 */
class KioskContext
{
    private ?KioskDevice $device = null;

    public function set(KioskDevice $device): void
    {
        $this->device = $device;
    }

    public function get(): ?KioskDevice
    {
        return $this->device;
    }

    public function has(): bool
    {
        return $this->device !== null;
    }

    /**
     * The resolved device, or a hard failure. Called by controllers behind
     * the `kiosk.auth` middleware, where a missing device means the
     * middleware was not attached — a routing bug, not a client error, so
     * it should surface loudly rather than as a null-check the controller
     * quietly handles.
     */
    public function deviceOrFail(): KioskDevice
    {
        if ($this->device === null) {
            throw new RuntimeException(
                'No kiosk device in context. Attach the kiosk.auth middleware to this route.',
            );
        }

        return $this->device;
    }

    public function forget(): void
    {
        $this->device = null;
    }
}
