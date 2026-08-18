<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per calendar year, incremented under a row-level
        // SELECT ... FOR UPDATE lock (App\Services\Billing\
        // InvoiceNumberGenerator) so concurrent invoice creation across
        // *different tenants* never produces a duplicate invoice number —
        // "tenant-safe" here means concurrency-safe across all tenants
        // sharing one global sequence, not a per-tenant counter (the
        // product document doesn't ask for per-tenant numbering, and a
        // single global sequence is simpler to reconcile against the
        // sequential paper trail tax/audit purposes expect).
        Schema::create('invoice_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_number_sequences');
    }
};
