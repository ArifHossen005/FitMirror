<?php

namespace App\Policies;

use App\Models\SizeChart;
use App\Models\User;

/**
 * A size chart has no owning single product (product_size_chart is
 * many-to-many — see the migration's own docblock), so unlike
 * ProductPolicy::manageSizeCharts() (which gates *attaching* a chart to one
 * specific product), chart-level CRUD needs its own policy rather than
 * reusing a method that requires a Product instance to check against.
 * Still gated on the `products.*` permissions, not a permission module of
 * its own — a chart only exists to be attached to a product.
 */
class SizeChartPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, SizeChart $chart): bool
    {
        return $this->sameTenant($user, $chart) && $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.update');
    }

    public function update(User $user, SizeChart $chart): bool
    {
        return $this->sameTenant($user, $chart) && $user->can('products.update');
    }

    public function delete(User $user, SizeChart $chart): bool
    {
        return $this->sameTenant($user, $chart) && $user->can('products.update');
    }

    private function sameTenant(User $user, SizeChart $chart): bool
    {
        return $user->tenant_id === $chart->tenant_id;
    }
}
