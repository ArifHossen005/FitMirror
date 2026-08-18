<?php

namespace App\Enums;

/**
 * Backed enum, not a MySQL ENUM column, per DOCUMENTATION.md §4.4.1.
 */
enum CouponStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
