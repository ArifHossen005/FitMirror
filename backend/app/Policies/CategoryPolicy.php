<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Governs Api\V1\Catalog\CategoryController. Every {category} route
 * parameter resolves through implicit model binding, which runs through
 * TenantScope — a cross-tenant id 404s before any method here executes.
 * The explicit tenant_id comparison below is defence in depth on top of
 * that, matching StorePolicy's own reasoning.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('categories.view');
    }

    public function view(User $user, Category $category): bool
    {
        return $this->sameTenant($user, $category) && $user->can('categories.view');
    }

    public function create(User $user): bool
    {
        return $user->can('categories.create');
    }

    /**
     * Drag-and-drop reordering acts on a whole sibling group at once, not
     * one category instance, so this — unlike update()/delete() — takes no
     * model: CategoryService::reorder() does its own tenant-ownership check
     * on every id in the payload.
     */
    public function reorder(User $user): bool
    {
        return $user->can('categories.update');
    }

    public function update(User $user, Category $category): bool
    {
        return $this->sameTenant($user, $category) && $user->can('categories.update');
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->sameTenant($user, $category) && $user->can('categories.delete');
    }

    private function sameTenant(User $user, Category $category): bool
    {
        return $user->tenant_id === $category->tenant_id;
    }
}
