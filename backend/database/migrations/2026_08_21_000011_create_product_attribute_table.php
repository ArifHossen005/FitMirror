<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Descriptive (non-variant-defining) attribute values attached to a
        // product as a whole — e.g. Fabric: Cotton, a Custom "Made in
        // Bangladesh" tag. Color/Size values that *do* define a variant axis
        // live on product_variants.color_attr_id/size_attr_id instead, never
        // here — see AttributeType::definesVariantAxis().
        Schema::create('product_attribute', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_id', 'attribute_value_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute');
    }
};
