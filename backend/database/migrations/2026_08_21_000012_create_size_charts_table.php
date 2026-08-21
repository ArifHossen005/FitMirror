<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('size_charts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Flexible measurement-row schema, per PROGRESS.md's own
            // wording — a tenant selling Panjabis needs chest/length rows, a
            // tenant selling Sarees needs none at all. Rather than a fixed
            // set of nullable measurement columns (which would fit neither
            // well), rows are stored as an ordered array of
            // {size, measurements: {label: value}} objects and rendered
            // as-is by both the dashboard editor and the kiosk popup.
            $table->json('rows');
            $table->string('unit', 8)->default('in');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('size_charts');
    }
};
