<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            // 0 = Sunday .. 6 = Saturday, matching Carbon::dayOfWeek so no
            // translation table is needed anywhere between PHP and SQL.
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_closed')->default(false);
            // TIME, not DATETIME — these are wall-clock opening hours that
            // recur weekly, interpreted in the store's own timezone
            // (stores.timezone), never a single absolute instant. Null
            // whenever is_closed is true.
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            // The kiosk may be allowed to run for a *narrower* window than
            // the shop's own trading hours (e.g. staffed hours only), so
            // these are separate columns rather than reusing opens_at /
            // closes_at. Null on either means "same as the shop hours".
            $table->time('kiosk_opens_at')->nullable();
            $table->time('kiosk_closes_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'day_of_week']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_hours');
    }
};
