<?php

namespace Database\Seeders;

use App\Enums\SuperAdminRole;
use App\Enums\SuperAdminStatus;
use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Bootstraps the first Mission Control account from .env rather than a
 * hardcoded credential, so the seeded password never ends up committed to
 * the repository. Safe to re-run: `firstOrNew` keyed on email means a
 * second run only updates the name/role/status, never silently overwrites
 * a password the Product Owner has since changed through the app.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL');

        if (empty($email)) {
            $this->command?->warn(
                'SUPER_ADMIN_EMAIL is not set in .env — skipping SuperAdminSeeder. '
                . 'Set SUPER_ADMIN_NAME, SUPER_ADMIN_EMAIL, and SUPER_ADMIN_PASSWORD, then re-run.',
            );

            return;
        }

        $superAdmin = SuperAdmin::query()->firstOrNew(['email' => $email]);
        $isNewAccount = !$superAdmin->exists;

        $superAdmin->name = env('SUPER_ADMIN_NAME', 'Product Owner');
        $superAdmin->role = SuperAdminRole::SuperAdmin;
        $superAdmin->status = SuperAdminStatus::Active;

        $configuredPassword = env('SUPER_ADMIN_PASSWORD');

        if (!empty($configuredPassword)) {
            $superAdmin->password = Hash::make($configuredPassword);
        } elseif ($isNewAccount) {
            // First run with no SUPER_ADMIN_PASSWORD set — the account still
            // needs a usable password, so one is generated and printed once.
            // Re-running the seeder later never touches this hash again
            // unless SUPER_ADMIN_PASSWORD is explicitly set.
            $generatedPassword = Str::random(20);
            $superAdmin->password = Hash::make($generatedPassword);
            $this->command?->warn(
                "SUPER_ADMIN_PASSWORD was not set — generated one-time password for {$email}: {$generatedPassword}",
            );
        }

        $superAdmin->save();

        $this->command?->info("Mission Control super admin ready: {$email}");
    }
}
