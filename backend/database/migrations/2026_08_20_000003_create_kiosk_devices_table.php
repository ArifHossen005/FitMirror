<?php

use App\Enums\KioskDeviceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');

            // The short human-typed code the staff member reads off the
            // dashboard and enters on the kiosk. Stored in the clear
            // deliberately, unlike the device token below: it is
            // *displayed* in the dashboard UI so it must be recoverable,
            // it expires in minutes, and it is single-use. Globally unique
            // because App\Services\Kiosk\KioskPairingService::claim()
            // resolves it before any tenant is known (the kiosk has no
            // credentials yet) — a per-tenant code would be ambiguous at
            // exactly the moment it has to be resolved.
            $table->string('pairing_code', 12)->nullable()->unique();
            $table->timestamp('pairing_code_expires_at')->nullable();

            // sha256 of the long-lived device token, never the token
            // itself — same reasoning as staff_invitations.token_hash and
            // Sanctum's own personal_access_tokens.token: a leaked DB dump
            // must not hand over working kiosk credentials.
            $table->string('device_token_hash', 64)->nullable()->unique();
            $table->timestamp('paired_at')->nullable();

            // Reported by the kiosk at claim time — a stable browser/device
            // identity used to detect "this code was claimed by a
            // different machine than the one that later heartbeats".
            $table->string('device_fingerprint')->nullable();
            $table->string('status')->default(KioskDeviceStatus::Pending->value);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_seen_ip', 45)->nullable();
            $table->string('app_version')->nullable();
            // Free-form health payload from the last heartbeat (camera
            // permission, battery, storage). JSON rather than columns
            // because the kiosk app's own health checks will grow through
            // Phase 6 without a schema change each time.
            $table->json('health')->nullable();
            // Display settings: language, theme, idle_timeout_seconds,
            // screensaver_playlist. Defaults live in
            // App\Models\KioskDevice::DEFAULT_SETTINGS so an older device
            // row missing a newly added key still resolves it.
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'store_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_devices');
    }
};
