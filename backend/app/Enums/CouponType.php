<?php

namespace App\Enums;

/**
 * Backed enum, not a MySQL ENUM column, per DOCUMENTATION.md §4.4.1.
 */
enum CouponType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
