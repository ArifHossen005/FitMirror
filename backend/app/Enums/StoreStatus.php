<?php

namespace App\Enums;

/**
 * Backed enum, not a MySQL ENUM column, per DOCUMENTATION.md §4.4.1.
 *
 * Distinct from "is the shop open right now" — that is a StoreHour
 * question, answered per weekday by App\Services\Store\StoreHoursService.
 * This is the branch's administrative state: whether the tenant considers
 * the branch part of the business at all.
 */
enum StoreStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Temporarily Inactive',
            self::Closed => 'Permanently Closed',
        };
    }

    /**
     * Whether a kiosk paired to this branch may run try-on sessions. Both
     * non-active states block the kiosk — the difference between them is
     * reporting intent (a closed branch is excluded from franchise
     * roll-ups, an inactive one is not), not capability.
     */
    public function isOperational(): bool
    {
        return $this === self::Active;
    }

    /**
     * Branches that count toward the plan's `branches` limit. A
     * permanently closed branch is kept for its historical sales/try-on
     * data but must not consume a paid slot, otherwise a tenant that
     * relocates a shop is silently charged for the old address forever.
     */
    public function countsTowardBranchLimit(): bool
    {
        return $this !== self::Closed;
    }
}
