<?php

namespace App\Enums;

/**
 * Backed enum, not a MySQL ENUM column, per DOCUMENTATION.md §4.4.1.
 *
 * A shift is a *plan* — "this staff member is rostered at this branch from
 * 09:00 to 17:00 on this date". Attendance (did they actually turn up) is
 * not modelled here and is not part of Phase 4; Cancelled exists so a
 * dropped shift stays visible on the schedule with its history, rather
 * than being deleted and silently reappearing as a gap.
 */
enum ShiftStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether this shift occupies its staff member's time — a cancelled
     * shift must not trigger the overlap rejection in
     * App\Services\Store\ShiftService.
     */
    public function occupiesStaff(): bool
    {
        return $this === self::Scheduled;
    }
}
