<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Entry point for `php artisan db:seed`. Each module registers its own
 * seeder class here as it's built, in dependency order (plans before
 * subscriptions, tenants before stores, etc.) — this class itself never
 * contains seeding logic, only the ordered `$this->call([...])` list.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            // PlanSeeder::class,           // Phase 3.A — Free/Pro/Max plans
            // SuperAdminSeeder::class,     // Phase 1.C — seeded from SUPER_ADMIN_EMAIL/.env
            // RolePermissionSeeder::class, // Phase 2.C — Owner/Manager/Staff roles
            // CategorySeeder::class,       // Phase 5.A — default Bangladeshi apparel taxonomy
        ]);
    }
}
