<?php

namespace App\Services\Media;

use App\Models\ProductImage;
use App\Models\Tenant;
use App\Services\BaseService;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tracks and enforces the plan's `storage_gb` limit. `tenants
 * .storage_bytes_used` is a denormalised running total (see its migration's
 * own docblock for why a live SUM() or App\Support\UsageCounter were both
 * rejected) — this class is the only place that column is written, kept in
 * sync transactionally on every upload/delete via increment()/decrement(),
 * with recalculate() as the drift-correcting backstop
 * (App\Console\Commands\RecalculateStorageUsage runs it on a schedule).
 */
class StorageQuotaService extends BaseService
{
    public function __construct(private readonly PlanService $plans) {}

    /**
     * @throws ValidationException
     */
    public function assertWithinQuota(Tenant $tenant, int $incomingBytes): void
    {
        $limit = $this->plans->limit($tenant, 'storage_gb');

        if ($limit === null) {
            return;
        }

        $prospectiveGb = (int) ceil(($tenant->storage_bytes_used + $incomingBytes) / 1_073_741_824);

        if ($prospectiveGb > $limit) {
            throw ValidationException::withMessages([
                'images' => ["This upload would exceed your plan's {$limit} GB storage limit."],
            ]);
        }
    }

    public function increment(Tenant $tenant, int $bytes): void
    {
        if ($bytes === 0) {
            return;
        }

        $tenant->increment('storage_bytes_used', $bytes);
    }

    public function decrement(Tenant $tenant, int $bytes): void
    {
        if ($bytes === 0) {
            return;
        }

        // No signed-underflow guard needed beyond this floor: bytes are
        // only ever decremented by a size this same tenant's own upload
        // previously incremented by, so the running total legitimately
        // never goes negative in practice — this is a defensive floor
        // against drift, not a case the codebase actually exercises.
        $tenant->update(['storage_bytes_used' => max(0, $tenant->storage_bytes_used - $bytes)]);
    }

    /**
     * Re-derives `storage_bytes_used` from the real SUM of every
     * `product_images.size_bytes` row the tenant owns, correcting any
     * drift the running total may have accumulated (a failed job that
     * incremented but never got to decrement on rollback, a manual DB
     * fix, etc.). Safe to run on any schedule — idempotent by definition.
     */
    public function recalculate(Tenant $tenant): int
    {
        $total = (int) ProductImage::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->sum('size_bytes');

        DB::table('tenants')->where('id', $tenant->id)->update(['storage_bytes_used' => $total]);

        return $total;
    }
}
