<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            // Bytes of the *original* upload, recorded once at upload time
            // — the single source StorageQuotaService sums to know a
            // tenant's storage usage, and what RecalculateStorageUsage
            // re-derives it from if the running total ever drifts.
            $table->unsignedBigInteger('size_bytes')->default(0)->after('path');
            // {"sm": "products/1/sm-....webp", "md": "...", "lg": "..."} —
            // populated by ProcessProductImageJob. A JSON column rather than
            // three nullable path columns, the same flexible-schema
            // instinct as size_charts.rows: a future size (e.g. "xl") needs
            // no migration.
            $table->json('derivatives')->nullable()->after('cdn_url');
            // Only ever set on a `gallery`-type row that has been submitted
            // for AI background removal — null means "never submitted".
            $table->string('background_removal_status')->nullable()->after('type');
            $table->unsignedTinyInteger('background_removal_attempts')->default(0)->after('background_removal_status');
            $table->string('background_removal_error')->nullable()->after('background_removal_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn([
                'size_bytes',
                'derivatives',
                'background_removal_status',
                'background_removal_attempts',
                'background_removal_error',
            ]);
        });
    }
};
