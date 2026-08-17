<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds tenant_id to Spatie's activity_log table so App\Models\Activity
 * (which uses BelongsToTenant, same as every other tenant-owned model) can
 * scope the audit log API to the acting user's tenant. No FK constraint —
 * Mission Control causer actions (Phase 13, e.g. tenant approval) log
 * against a tenant the SuperAdmin doesn't belong to, and activity rows
 * should outlive a tenant's own deletion for compliance/audit purposes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('activitylog.database_connection'))
            ->table(config('activitylog.table_name'), function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
    }

    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))
            ->table(config('activitylog.table_name'), function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
    }
};
