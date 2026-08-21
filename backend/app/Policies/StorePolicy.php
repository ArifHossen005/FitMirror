<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

/**
 * Governs the branch-management surface (Api\V1\Store\StoreController and
 * the hours/kiosk/shift controllers that hang off a branch).
 *
 * Every {store} route parameter resolves through Laravel's implicit model
 * binding, which runs through TenantScope — a cross-tenant id 404s before
 * any method here executes. The explicit tenant_id comparison below is
 * defence in depth on top of that, matching UserPolicy's own reasoning.
 *
 * Permission strings come from config/permissions.php, seeded since Phase
 * 2.C: `stores.*` for the branch itself, `kiosk.*` for the devices in it.
 * Per that config's default grants, a Manager may create and update
 * branches but only an Owner may delete one.
 */
class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stores.view');
    }

    public function view(User $user, Store $store): bool
    {
        return $this->sameTenant($user, $store) && $user->can('stores.view');
    }

    public function create(User $user): bool
    {
        return $user->can('stores.create');
    }

    public function update(User $user, Store $store): bool
    {
        return $this->sameTenant($user, $store) && $user->can('stores.update');
    }

    public function delete(User $user, Store $store): bool
    {
        return $this->sameTenant($user, $store) && $user->can('stores.delete');
    }

    /**
     * Opening hours are a branch setting, not a kiosk one, so they follow
     * `stores.update` — a Staff account with kiosk.view can read the hours
     * their kiosk runs on but cannot change them.
     */
    public function manageHours(User $user, Store $store): bool
    {
        return $this->update($user, $store);
    }

    /**
     * Kiosk devices are gated on `kiosk.manage` rather than `stores.update`
     * — pairing and unpairing hardware is a floor-staff operation a Manager
     * has by default, and is deliberately separable from editing the
     * branch's own record.
     */
    public function manageKiosks(User $user, Store $store): bool
    {
        return $this->sameTenant($user, $store) && $user->can('kiosk.manage');
    }

    public function viewKiosks(User $user, Store $store): bool
    {
        return $this->sameTenant($user, $store) && $user->can('kiosk.view');
    }

    /**
     * Rostering is staff management, not branch management — a Manager who
     * can invite and update staff can also schedule them; a Staff account
     * (which has neither) cannot.
     */
    public function manageShifts(User $user, Store $store): bool
    {
        return $this->sameTenant($user, $store) && $user->can('staff.update');
    }

    private function sameTenant(User $user, Store $store): bool
    {
        return $user->tenant_id === $store->tenant_id;
    }
}
