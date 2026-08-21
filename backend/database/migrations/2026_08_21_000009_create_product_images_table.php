<?php

use App\Enums\ProductImageType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Null means "belongs to the product generally" (e.g. a
            // lifestyle shot); set means "specific to this color/size", the
            // same shape most storefronts use for a color-swatch gallery.
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('disk')->default('tenant');
            $table->string('path');
            $table->string('cdn_url')->nullable();
            $table->string('type')->default(ProductImageType::Gallery->value);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
