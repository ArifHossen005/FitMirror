<?php

namespace App\Policies;

use App\Models\Occasion;
use App\Models\User;

class OccasionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('occasions.view');
    }

    public function view(User $user, Occasion $occasion): bool
    {
        return $this->sameTenant($user, $occasion) && $user->can('occasions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('occasions.create');
    }

    public function update(User $user, Occasion $occasion): bool
    {
        return $this->sameTenant($user, $occasion) && $user->can('occasions.update');
    }

    public function delete(User $user, Occasion $occasion): bool
    {
        return $this->sameTenant($user, $occasion) && $user->can('occasions.delete');
    }

    private function sameTenant(User $user, Occasion $occasion): bool
    {
        return $user->tenant_id === $occasion->tenant_id;
    }
}
