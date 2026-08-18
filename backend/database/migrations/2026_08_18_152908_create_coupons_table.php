<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type');
            // percentage: 1-100. fixed: whole-taka amount, same unit as
            // plans.price_monthly (see Plan's own docblock).
            $table->unsignedInteger('value');
            // Plan slugs this coupon may be applied to; null means every
            // plan is eligible.
            $table->json('applies_to_plans')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('per_tenant_limit')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
