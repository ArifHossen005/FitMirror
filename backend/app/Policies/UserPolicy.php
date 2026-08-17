<?php

namespace App\Policies;

use App\Models\User;

/**
 * Governs one tenant User acting on another — the staff management surface
 * (Api\V1\Staff\*Controller). Every method assumes $user is already
 * authenticated inside a resolved tenant context (TenantContext); cross-
 * tenant access is blocked explicitly below rather than relied upon to be
 * impossible, since $target is loaded via User::withoutTenantScope() in
 * the controllers (see StaffController's docblock) precisely so a 404 can
 * be told apart from a 403.
 *
 * The tenant owner (Tenant::owner_id) is structurally protected here, not
 * just by convention: no permission grant lets a Manager (or another
 * Owner-permissioned account) demote, deactivate, or delete the actual
 * owner account. Only the owner may act on their own record.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('staff.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->tenant_id === $target->tenant_id && $user->can('staff.view');
    }

    public function create(User $user): bool
    {
        return $user->can('staff.invite');
    }

    public function update(User $user, User $target): bool
    {
        if ($user->tenant_id !== $target->tenant_id) {
            return false;
        }

        if ($target->isTenantOwner() && $user->id !== $target->id) {
            return false;
        }

        return $user->can('staff.update');
    }

    public function deactivate(User $user, User $target): bool
    {
        if ($user->tenant_id !== $target->tenant_id) {
            return false;
        }

        if ($target->id === $user->id || $target->isTenantOwner()) {
            return false;
        }

        return $user->can('staff.deactivate');
    }

    public function delete(User $user, User $target): bool
    {
        if ($user->tenant_id !== $target->tenant_id) {
            return false;
        }

        if ($target->id === $user->id || $target->isTenantOwner()) {
            return false;
        }

        return $user->can('staff.delete');
    }
}
