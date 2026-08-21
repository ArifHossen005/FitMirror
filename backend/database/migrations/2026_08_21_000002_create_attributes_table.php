<?php

use App\Enums\AttributeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            // AttributeType::class — color/size/fabric/custom. String, not a
            // MySQL ENUM column, per DOCUMENTATION.md §4.4.1.
            $table->string('type');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default(AttributeStatus::Active->value);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
