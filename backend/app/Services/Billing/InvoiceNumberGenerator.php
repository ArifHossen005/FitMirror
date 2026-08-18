<?php

namespace App\Services\Billing;

use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * Sequential, concurrency-safe invoice numbers shaped `INV-{year}-{6
 * digits}`, e.g. `INV-2026-000001`. One global sequence shared by every
 * tenant (not a per-tenant counter — see the invoice_number_sequences
 * migration's own docblock for why that reading of "tenant-safe" was
 * chosen), reset each calendar year.
 */
class InvoiceNumberGenerator extends BaseService
{
    public function next(): string
    {
        $year = (int) now()->format('Y');

        return $this->transaction(function () use ($year) {
            // lockForUpdate() inside a transaction is what makes this
            // concurrency-safe: two invoices created in the same instant
            // (by different tenants, different web workers) serialize on
            // this row instead of both reading the same last_number and
            // racing to increment it.
            DB::table('invoice_number_sequences')->updateOrInsert(['year' => $year], []);

            $sequence = DB::table('invoice_number_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $nextNumber = $sequence->last_number + 1;

            DB::table('invoice_number_sequences')
                ->where('year', $year)
                ->update(['last_number' => $nextNumber]);

            return sprintf('INV-%d-%06d', $year, $nextNumber);
        });
    }
}
