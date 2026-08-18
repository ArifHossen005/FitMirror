<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('gateway');
            // Our own tran_id, generated at initiate time — unique per
            // gateway so a retried initiate or a replayed IPN can always
            // be matched back to exactly one payment attempt.
            $table->string('gateway_txn_id')->unique();
            // SSLCommerz's val_id, present only once a session has been
            // completed and is ready for order-validation.
            $table->string('val_id')->nullable();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('BDT');
            $table->string('method')->nullable();
            $table->string('status');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('val_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
