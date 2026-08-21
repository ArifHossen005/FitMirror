<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Governs Api\V1\Product\ProductController and the size-chart/price-history
 * controllers that hang off a product. `publish`/`unpublish` use the
 * dedicated `products.publish` permission (Manager and Owner only, per
 * config/permissions.php) — a Staff account with `products.view` can never
 * change what's visible on the kiosk even though it can read the catalog.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->sameTenant($user, $product) && $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->sameTenant($user, $product) && $user->can('products.update');
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->sameTenant($user, $product) && $user->can('products.delete');
    }

    public function publish(User $user, Product $product): bool
    {
        return $this->sameTenant($user, $product) && $user->can('products.publish');
    }

    /**
     * Size charts are a catalog-authoring concern, gated the same as
     * editing the product itself.
     */
    public function manageSizeCharts(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    private function sameTenant(User $user, Product $product): bool
    {
        return $user->tenant_id === $product->tenant_id;
    }
}
