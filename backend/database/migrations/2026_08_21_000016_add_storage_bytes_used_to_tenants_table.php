<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // A denormalised running total, not a live SUM(product_images
            // .size_bytes) — the plan's `storage_gb` limit is checked on
            // every image upload, and a SUM across every image the tenant
            // has ever uploaded would get slower with every product they
            // add. Kept accurate by StorageQuotaService incrementing/
            // decrementing it transactionally on every upload/delete, with
            // the `storage:recalculate` command as a self-healing backstop
            // against drift (Redis-backed App\Support\UsageCounter was
            // considered and rejected for this — its resetAll() wipes every
            // metric nightly, which is correct for a daily quota like
            // try-on sessions but wrong for a cumulative gauge that must
            // never zero out on its own).
            $table->unsignedBigInteger('storage_bytes_used')->default(0)->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('storage_bytes_used');
        });
    }
};
