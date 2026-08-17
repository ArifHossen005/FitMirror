<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `tenants.plan_id` was created without a foreign key in Phase 2.A
 * (`plans` didn't exist yet — see that migration's own comment). Adding it
 * now that it does; `nullOnDelete()` since a tenant must survive a plan
 * being retired even though that should never actually happen in
 * practice (plans are archived via `status`, not deleted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
        });
    }
};
