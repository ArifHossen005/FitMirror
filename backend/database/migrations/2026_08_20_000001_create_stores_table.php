<?php

use App\Enums\StoreStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Tenant-authored short code shown on receipts and kiosk
            // screens ("DHK-01"). Unique per tenant, not globally — two
            // unrelated shops both calling their flagship "MAIN" is
            // expected, not a conflict.
            $table->string('code');
            $table->boolean('is_main')->default(false);
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('area')->nullable();
            // 7 decimal places ~= 1.1cm of precision, well past what a
            // storefront pin needs, and decimal rather than float so two
            // reads of the same row always compare equal.
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('map_url', 2048)->nullable();
            // Relative paths on the `tenant` disk (App\Support\TenantStorage),
            // never absolute URLs — the disk is local in dev and S3/R2 in
            // production, so the public URL is resolved at read time.
            $table->string('logo')->nullable();
            $table->string('banner')->nullable();
            // { "facebook": "https://...", "instagram": "...", ... } — a
            // JSON column rather than one nullable string per network, so
            // adding a network later is not a migration.
            $table->json('socials')->nullable();
            $table->string('timezone')->default('Asia/Dhaka');
            $table->string('status')->default(StoreStatus::Active->value);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
