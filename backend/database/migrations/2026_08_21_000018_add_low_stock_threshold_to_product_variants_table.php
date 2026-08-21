<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // Null means "no alert configured" for this variant, not
            // "zero" — a threshold of 0 is a legitimate, meaningful
            // setting ("only warn me when it's completely gone"), so it
            // must stay distinguishable from "never warn me at all".
            // Compared against the tenant-wide aggregate `stock`, not a
            // per-branch figure — see StockService's own docblock for why
            // that scope was kept bounded here.
            $table->unsignedInteger('low_stock_threshold')->nullable()->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('low_stock_threshold');
        });
    }
};
