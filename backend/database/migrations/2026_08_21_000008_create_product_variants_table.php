<?php

use App\Enums\ProductVariantStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku');
            // nullOnDelete(), not restrict: removing a color/size value from
            // the attribute library must not be blocked by every historical
            // variant that once used it — the variant just loses that facet
            // of its label. ProductVariantService still blocks *creating* a
            // variant against a soft-deleted attribute value.
            $table->foreignId('color_attr_id')->nullable()->constrained('attribute_values')->nullOnDelete();
            $table->foreignId('size_attr_id')->nullable()->constrained('attribute_values')->nullOnDelete();
            // Nullable: null means "use the product's own base_price/
            // sale_price", so a variant only needs a row here when its price
            // genuinely differs (e.g. a larger size costs more fabric).
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->string('barcode')->nullable();
            $table->string('status')->default(ProductVariantStatus::Active->value);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'sku']);
            $table->unique(['product_id', 'color_attr_id', 'size_attr_id'], 'product_variants_axis_unique');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
