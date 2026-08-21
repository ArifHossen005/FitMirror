<?php

namespace App\Enums;

/**
 * Backed enum, not a MySQL ENUM column, per DOCUMENTATION.md §4.4.1.
 *
 * The lifecycle of one physical kiosk (a tablet or laptop in a branch):
 * the tenant registers it in the dashboard (Pending, holding a short-lived
 * pairing code), the device itself redeems that code for a long-lived
 * device token (Paired), and the tenant can later suspend it without
 * losing its identity/settings, or unpair it entirely (back to Pending
 * with a fresh code).
 */
enum KioskDeviceStatus: string
{
    case Pending = 'pending';
    case Paired = 'paired';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting Pairing',
            self::Paired => 'Paired',
            self::Suspended => 'Suspended',
        };
    }

    /**
     * Whether a request carrying this device's token should be honoured.
     * Only Paired qualifies — a suspended device keeps its token row (so
     * un-suspending is instant and does not require re-pairing the
     * hardware) but every authenticated kiosk request is rejected while
     * suspended.
     */
    public function canAuthenticate(): bool
    {
        return $this === self::Paired;
    }
}
