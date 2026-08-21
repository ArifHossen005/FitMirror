<?php

namespace App\Policies;

use App\Models\Attribute;
use App\Models\User;

class AttributePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attributes.view');
    }

    public function view(User $user, Attribute $attribute): bool
    {
        return $this->sameTenant($user, $attribute) && $user->can('attributes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('attributes.create');
    }

    public function update(User $user, Attribute $attribute): bool
    {
        return $this->sameTenant($user, $attribute) && $user->can('attributes.update');
    }

    public function delete(User $user, Attribute $attribute): bool
    {
        return $this->sameTenant($user, $attribute) && $user->can('attributes.delete');
    }

    private function sameTenant(User $user, Attribute $attribute): bool
    {
        return $user->tenant_id === $attribute->tenant_id;
    }
}
