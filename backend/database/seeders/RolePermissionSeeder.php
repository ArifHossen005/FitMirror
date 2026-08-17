<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the tenant-side RBAC surface from config/permissions.php: one
 * Permission row per module.action pair, then the Owner/Manager/Staff
 * roles with their default grants. Guard is always 'web' — see
 * App\Models\User::$guard_name.
 *
 * Idempotent via findOrCreate()/syncPermissions() — safe to re-run after
 * config/permissions.php gains a new module or action; existing
 * tenant->role assignments (model_has_roles) are untouched since roles are
 * looked up by name, never recreated.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Stale cached permission/role lookups would otherwise let a
        // previous test or request see permissions that no longer match
        // what's about to be (re)synced below.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissionNames = $this->createPermissions();

        // findOrCreate() above can populate PermissionRegistrar's internal
        // name+guard lookup cache from before some of these rows existed —
        // without this, syncPermissions() below throws
        // PermissionDoesNotExist for any permission created earlier in the
        // very same run.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Config::array('permissions.role_permissions') as $roleName => $grants) {
            $role = Role::findOrCreate($roleName, 'web');

            $permissionNames = in_array('*', $grants, true)
                ? $allPermissionNames
                : $grants;

            $role->syncPermissions($permissionNames);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    private function createPermissions(): array
    {
        $names = [];

        foreach (Config::array('permissions.modules') as $module => $actions) {
            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                Permission::findOrCreate($name, 'web');
                $names[] = $name;
            }
        }

        return $names;
    }
}
