<?php

use App\Enums\CategoryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Self-referencing adjacency list, per PROGRESS.md's own field
            // spec — no nested-set/materialized-path package exists in this
            // codebase (nothing in Phase 1-4 needed a tree), so
            // CategoryService walks parent_id directly. restrictOnDelete()
            // is a DB-level backstop for the same invariant
            // CategoryService::delete() checks first: a category with
            // children cannot be removed out from under them.
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('icon')->nullable();
            $table->string('image')->nullable();
            $table->string('gender')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default(CategoryStatus::Active->value);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'parent_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
