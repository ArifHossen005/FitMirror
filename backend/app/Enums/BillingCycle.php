<?php

namespace App\Enums;

/**
 * Backed enum, not a MySQL ENUM column, per DOCUMENTATION.md §4.4.1.
 */
enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }
}
