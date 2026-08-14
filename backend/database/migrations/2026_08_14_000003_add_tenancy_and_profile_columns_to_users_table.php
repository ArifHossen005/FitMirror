<?php

use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the default Laravel scaffold `users` table (0001_01_01_000000_...)
 * rather than rewriting that migration — it already ran in every existing
 * environment. See PROGRESS.md Phase 2.B.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();

            // No foreignId()/constrained() — `stores` doesn't exist until
            // Phase 4.A. Same pattern as tenants.plan_id (see that
            // migration's comment); the FK constraint is added once the
            // table it references exists.
            $table->unsignedBigInteger('store_id')->nullable()->after('tenant_id');

            $table->string('phone')->nullable()->after('email');
            $table->string('avatar')->nullable()->after('password');
            $table->string('locale', 5)->default('bn')->after('avatar');
            $table->string('status')->default(UserStatus::Active->value)->after('locale');
            $table->timestamp('last_login_at')->nullable()->after('status');

            // TOTP 2FA — same shape as super_admins, encrypted at rest via
            // the model's casts (see App\Models\User::casts()).
            $table->text('two_factor_secret')->nullable()->after('last_login_at');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');

            $table->softDeletes();

            // Email stays globally unique (the default scaffold migration
            // already enforced this) — deliberately not scoped per-tenant.
            // Login (POST /api/v1/auth/login) looks a user up by email
            // alone, before any tenant is known from the request, so two
            // different tenants sharing an email would make that lookup
            // ambiguous. A person who genuinely works at two shops needs
            // two separate accounts/emails.
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn([
                'store_id',
                'phone',
                'avatar',
                'locale',
                'status',
                'last_login_at',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
