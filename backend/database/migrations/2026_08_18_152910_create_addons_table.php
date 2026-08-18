<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->unsignedInteger('price');
            $table->string('currency', 3)->default('BDT');
            // How much of the resource one purchase grants — e.g. 500 for
            // an "SMS Pack (500)", 10 for a "Storage Pack (10 GB)", 30 for
            // a "Priority Support (30 days)" pack. Every addon is modelled
            // as a consumable balance so App\Services\Billing\
            // AddonConsumptionService has one uniform draw-down mechanism
            // instead of a nullable "unlimited/boolean" special case.
            $table->unsignedInteger('unit_amount');
            $table->string('status')->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
