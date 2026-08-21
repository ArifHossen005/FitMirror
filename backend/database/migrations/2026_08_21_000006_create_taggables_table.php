<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Polymorphic pivot — no `spatie/laravel-tags` or similar package is
        // installed anywhere in this codebase (checked composer.json), so
        // tagging is hand-rolled the same way product_occasion/
        // product_attribute are: a plain pivot, no id, no timestamps, no
        // tenant_id. A morphToMany's sync()/attach() perform raw bulk
        // inserts that only ever populate the pivot's own two/three
        // columns (tag_id, taggable_type, taggable_id) — there is no clean
        // way to make every call site also supply tenant_id short of
        // Eloquent's withPivotValue(), which would then also constrain
        // every *read* of the relation to a fixed value baked in at
        // relation-definition time, breaking a query built from an empty
        // model instance (e.g. Product::query()->whereHas('tags', ...)).
        // Isolation is enforced the same way it is for product_occasion:
        // every query reaches this table through Tag's or Product's own
        // already tenant-scoped relation, never directly.
        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');

            $table->unique(['tag_id', 'taggable_type', 'taggable_id'], 'taggables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
    }
};
