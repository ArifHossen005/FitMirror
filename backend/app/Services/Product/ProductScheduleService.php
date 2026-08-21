<?php

namespace App\Services\Product;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\BaseService;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;

/**
 * Applies `publish_at`/`unpublish_at` — the season/expiry scheduler
 * PROGRESS.md's 5.D checklist asks for. Called per-tenant by
 * App\Console\Commands\ApplyProductSchedule, itself wrapped in
 * TenantContext::runAs() the way Decision D-26 requires for any
 * artisan-invoked code touching a BelongsToTenant model.
 */
class ProductScheduleService extends BaseService
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @return array{published: int, unpublished: int}
     */
    public function applyFor(Tenant $tenant): array
    {
        return $this->tenantContext->runAs($tenant, function () {
            $now = Carbon::now();

            $published = Product::query()
                ->where('status', ProductStatus::Draft->value)
                ->whereNotNull('publish_at')
                ->where('publish_at', '<=', $now)
                ->update(['status' => ProductStatus::Published->value]);

            $unpublished = Product::query()
                ->where('status', ProductStatus::Published->value)
                ->whereNotNull('unpublish_at')
                ->where('unpublish_at', '<=', $now)
                ->update(['status' => ProductStatus::Draft->value]);

            return ['published' => $published, 'unpublished' => $unpublished];
        });
    }
}
