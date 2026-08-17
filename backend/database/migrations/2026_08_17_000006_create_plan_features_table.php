<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key');
            $table->boolean('enabled')->default(false);
            // Tier detail for features that aren't simple on/off — e.g.
            // analytics is enabled on every plan but its meta.tier differs
            // ("basic"/"advanced"/"full_ai"); campaign_manager's meta.tier
            // differs between Pro ("basic") and Max ("full_ai") even though
            // both are `enabled=true`.
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
