<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One size chart may be reused across many products (a tenant's
        // "Men's Panjabi" chart applies to every Panjabi they sell), so this
        // is a many-to-many link, not a size_chart_id column on products —
        // duplicating rows onto every product would make a single
        // measurement correction an N-row update instead of one.
        Schema::create('product_size_chart', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('size_chart_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_id', 'size_chart_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_size_chart');
    }
};
