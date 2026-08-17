<?php

namespace App\Enums;

/**
 * Backed enum, not a MySQL ENUM column, per DOCUMENTATION.md §4.4.1.
 */
enum PlanStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Archived => 'Archived',
        };
    }
}
