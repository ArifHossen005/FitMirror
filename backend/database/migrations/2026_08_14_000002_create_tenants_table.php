<?php

use App\Enums\TenantStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subdomain')->unique();
            $table->string('custom_domain')->nullable()->unique();

            // Nullable because tenant provisioning creates the Tenant row
            // before the owner User exists (the User needs tenant_id, which
            // doesn't exist until Phase 2.B) — the provisioning service
            // backfills this once the owner is created.
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default(TenantStatus::Pending->value);
            $table->timestamp('trial_ends_at')->nullable();

            // No foreignId()/constrained() here — the `plans` table doesn't
            // exist until Phase 3.A. A follow-up Phase 3.A migration adds
            // the FK constraint once it does; this column is real and
            // usable today, just unconstrained.
            $table->unsignedBigInteger('plan_id')->nullable();

            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
