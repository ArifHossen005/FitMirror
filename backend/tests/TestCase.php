<?php

namespace Tests;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every RefreshDatabase test gets the real Owner/Manager/Staff roles +
     * permission matrix, and the real Free/Pro/Max plans, seeded.
     * RegistrationService::register() assigns the 'owner' role
     * unconditionally, and PlanService::resolve() (consulted by
     * GET /auth/me for every authenticated request) falls back to
     * Plan::free() for any tenant with no plan chosen yet — both throw
     * without this. Deliberately not the full DatabaseSeeder —
     * SuperAdminSeeder depends on .env credentials tests shouldn't need,
     * and CategorySeeder doesn't exist yet.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->seed(RolePermissionSeeder::class);
            $this->seed(PlanSeeder::class);
        }
    }
}
