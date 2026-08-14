<?php

use App\Enums\SuperAdminRole;
use App\Enums\SuperAdminStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deliberately its own table, not a row in `users` — Mission Control
 * authenticates via a completely separate guard (config/auth.php
 * `super_admin`) so a tenant user token can never be mistaken for
 * platform-owner access. See PROGRESS.md Phase 1.C.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('super_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');

            // TOTP 2FA (pragmarx/google2fa-laravel) — both columns are
            // stored encrypted via the model's casts, never in plaintext.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->string('role')->default(SuperAdminRole::SuperAdmin->value);
            $table->string('status')->default(SuperAdminStatus::Active->value);

            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('super_admins');
    }
};
