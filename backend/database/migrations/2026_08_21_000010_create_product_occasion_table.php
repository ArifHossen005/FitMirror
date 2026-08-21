<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pure join table between two already tenant-scoped models — every
        // query reaches it through Product's or Occasion's own
        // BelongsToMany relation, so (unlike `taggables`) it carries no
        // tenant_id of its own, matching plain Eloquent pivot convention.
        Schema::create('product_occasion', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('occasion_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_id', 'occasion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_occasion');
    }
};
