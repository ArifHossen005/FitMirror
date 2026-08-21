<?php

namespace App\Enums;

/**
 * Backed enum, not a MySQL ENUM column, per DOCUMENTATION.md §4.4.1 (same
 * convention as StoreStatus).
 */
enum CategoryStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    /**
     * Categories the plan's `categories` limit counts against. An inactive
     * category is hidden from the catalog and kiosk but still consumes a
     * slot — unlike a permanently closed branch (StoreStatus), there is no
     * "this category no longer exists for billing purposes" state, since
     * products still reference it.
     */
    public function countsTowardLimit(): bool
    {
        return true;
    }
}
