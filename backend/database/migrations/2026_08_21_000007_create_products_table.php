<?php

use App\Enums\ProductStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Nullable: a product can be shared across every branch (no
            // single owning store) — per-branch on-hand quantity is tracked
            // per variant instead (Phase 5.D's stock_movements.store_id),
            // this column is only "which branch catalogued it", not "where
            // it's stocked".
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            // restrictOnDelete(), not cascade: deleting a category must not
            // silently delete every product in it. CategoryService::delete()
            // checks for this up front; the FK is the backstop, same
            // categories.parent_id reasoning.
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('sku');
            $table->text('description')->nullable();
            $table->string('brand')->nullable();
            $table->decimal('base_price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('status')->default(ProductStatus::Draft->value);
            // Set true only once Phase 5.C's RemoveBackgroundJob succeeds on
            // at least one image — see ProductImage.type Tryon.
            $table->boolean('is_tryon_ready')->default(false);
            $table->string('season')->nullable();
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('unpublish_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'sku']);
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
