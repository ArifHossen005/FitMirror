<?php

namespace App\Services\Staff;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\BaseService;

/**
 * Multi-step mutations on an existing staff User — role changes and
 * deactivation both need to revoke standing Sanctum tokens as part of the
 * same operation, which is why they live here rather than as a bare
 * Eloquent update() in the controller (Decision D-09).
 */
class StaffService extends BaseService
{
    public function updateRole(User $target, string $role): User
    {
        return $this->transaction(function () use ($target, $role) {
            $target->syncRoles([$role]);

            return $target;
        });
    }

    /**
     * Deactivating a staff member revokes every standing session — an
     * account marked suspended that keeps a live Sanctum token would still
     * be able to call every route until that token separately expired.
     */
    public function deactivate(User $target): User
    {
        return $this->transaction(function () use ($target) {
            $target->forceFill(['status' => UserStatus::Suspended])->save();
            $target->tokens()->delete();

            return $target;
        });
    }

    public function reactivate(User $target): User
    {
        $target->forceFill(['status' => UserStatus::Active])->save();

        return $target;
    }

    public function delete(User $target): void
    {
        $this->transaction(function () use ($target) {
            $target->tokens()->delete();
            $target->delete();
        });
    }
}
