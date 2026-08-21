<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tags.view');
    }

    public function view(User $user, Tag $tag): bool
    {
        return $this->sameTenant($user, $tag) && $user->can('tags.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tags.create');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $this->sameTenant($user, $tag) && $user->can('tags.update');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $this->sameTenant($user, $tag) && $user->can('tags.delete');
    }

    private function sameTenant(User $user, Tag $tag): bool
    {
        return $user->tenant_id === $tag->tenant_id;
    }
}
