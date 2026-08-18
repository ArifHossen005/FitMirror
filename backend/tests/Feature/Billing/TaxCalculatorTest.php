<?php

namespace Tests\Feature\Billing;

use App\Support\TaxCalculator;
use Tests\TestCase;

class TaxCalculatorTest extends TestCase
{
    public function test_vat_is_15_percent_by_default_rounded_to_the_nearest_taka(): void
    {
        $this->assertSame(75, TaxCalculator::vatFor(499)); // 74.85 -> 75
        $this->assertSame(195, TaxCalculator::vatFor(1299)); // 194.85 -> 195
        $this->assertSame(0, TaxCalculator::vatFor(0));
    }

    public function test_vat_rounds_half_up_at_the_taka_boundary(): void
    {
        // 10 * 0.15 = 1.5, rounds to 2 (PHP round() half-away-from-zero).
        $this->assertSame(2, TaxCalculator::vatFor(10));
    }

    public function test_a_configured_vat_rate_is_honoured(): void
    {
        config(['tax.vat_rate' => 0.10]);

        $this->assertSame(50, TaxCalculator::vatFor(500));
    }
}
