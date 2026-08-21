<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            // App\Enums\StockMovementType — Adjustment, TransferIn,
            // TransferOut. Purely descriptive: `quantity` already carries
            // the signed delta this row applied at $store_id, so a report
            // never has to re-derive direction from the type.
            $table->string('type');
            // Signed — positive increases this store's on-hand for this
            // variant, negative decreases it. product_variants.stock (the
            // tenant-wide aggregate) only moves for an Adjustment; a
            // matched TransferOut/TransferIn pair nets to zero across the
            // two stores, so the aggregate is deliberately left untouched
            // by a transfer.
            $table->integer('quantity');
            // Shared by a TransferOut/TransferIn pair (a UUID) so the two
            // halves of one transfer can be found and displayed together;
            // null for a plain Adjustment, which has no counterpart row.
            $table->string('reference')->nullable();
            $table->string('note')->nullable();
            // nullOnDelete(): a movement is an immutable ledger entry —
            // the acting staff member leaving the tenant must not delete
            // or block deletion of the record of what they did, same
            // reasoning as price_history.user_id.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'variant_id', 'store_id']);
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
