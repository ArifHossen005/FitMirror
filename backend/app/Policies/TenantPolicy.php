<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

/**
 * Governs a tenant-side User's access to their *own* tenant record — Tenant
 * as an entity Mission Control administers cross-tenant is a separate
 * concern (routes/api_mission.php, guarded by super_admin, never routes
 * through this policy). Auto-discovered by Laravel's Model-to-Policy
 * convention (App\Models\Tenant -> App\Policies\TenantPolicy), no manual
 * registration needed — verified by
 * tests/Feature/Rbac/TenantPolicyTest.php.
 */
class TenantPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id;
    }

    /**
     * Only 'tenant_settings.update' holders (Owner by default — see
     * config/permissions.php) may change the tenant record itself. No
     * update endpoint exists yet (Phase 4+ builds tenant settings pages);
     * this policy exists now so that endpoint can call authorize() on day
     * one instead of the check being invented ad hoc later.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id && $user->can('tenant_settings.update');
    }

    /**
     * Tenant self-deletion is not exposed via any route today — soft-delete
     * with data retention rules is explicitly deferred (PROGRESS.md Phase
     * 2.A "tenant teardown service"). This method exists so the policy
     * class is complete and ready the moment that service lands, rather
     * than being retrofitted.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id && $user->isTenantOwner();
    }
}
