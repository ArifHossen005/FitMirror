<?php

namespace Tests\Feature\Payment;

use App\Services\Billing\InvoiceNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_are_sequential_and_shaped_correctly(): void
    {
        $generator = app(InvoiceNumberGenerator::class);
        $year = now()->format('Y');

        $first = $generator->next();
        $second = $generator->next();
        $third = $generator->next();

        $this->assertSame("INV-{$year}-000001", $first);
        $this->assertSame("INV-{$year}-000002", $second);
        $this->assertSame("INV-{$year}-000003", $third);
    }

    public function test_the_sequence_row_persists_the_last_issued_number(): void
    {
        $generator = app(InvoiceNumberGenerator::class);
        $year = (int) now()->format('Y');

        $generator->next();
        $generator->next();

        $this->assertDatabaseHas('invoice_number_sequences', ['year' => $year, 'last_number' => 2]);
    }
}
