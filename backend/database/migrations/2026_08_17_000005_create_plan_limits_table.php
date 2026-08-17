<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            // Null means unlimited — App\Services\Plan\PlanService treats a
            // missing row the same as a null value (both "unlimited"), so a
            // plan simply omitting a key never accidentally caps it at 0.
            $table->unsignedInteger('value')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_limits');
    }
};
