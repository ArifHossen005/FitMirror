<?php

namespace App\Services\Inventory;

use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds every variant currently at or below its own `low_stock_threshold`
 * for one tenant — App\Console\Commands\CheckLowStock's per-tenant scan.
 * Queries via withoutTenantScope() with an explicit tenant_id filter,
 * never relying on an ambient TenantContext (the same "bypass, don't
 * depend on ambient state" choice StorageQuotaService::recalculate()
 * makes, and simpler than wrapping every call site in
 * TenantContext::runAs() per Decision D-26 for a read-only scan).
 */
class LowStockDetectionService extends BaseService
{
    /**
     * @return Collection<int, ProductVariant>
     */
    public function detectFor(Tenant $tenant): Collection
    {
        return ProductVariant::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('low_stock_threshold')
            ->whereColumn('stock', '<=', 'low_stock_threshold')
            ->with('product:id,name')
            ->get();
    }
}
