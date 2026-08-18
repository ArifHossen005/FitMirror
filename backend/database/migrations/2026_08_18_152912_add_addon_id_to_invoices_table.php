<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Nullable and mutually exclusive with subscription_id — a plan
            // purchase invoice sets subscription_id, an add-on purchase
            // invoice sets this instead. See App\Services\Billing\
            // PaymentService::finalizeInvoice()'s docblock.
            $table->foreignId('addon_id')->nullable()->after('subscription_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('addon_id');
        });
    }
};
