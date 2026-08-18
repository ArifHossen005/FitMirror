<?php

namespace App\Support;

/**
 * VAT is computed on the post-discount taxable amount (subtotal minus any
 * coupon discount) — standard Bangladeshi VAT practice, and consistent
 * with charging tax on what the customer actually pays, not the pre-
 * discount list price.
 */
class TaxCalculator
{
    public static function vatFor(int $taxableAmount): int
    {
        return (int) round($taxableAmount * config('tax.vat_rate'));
    }
}
