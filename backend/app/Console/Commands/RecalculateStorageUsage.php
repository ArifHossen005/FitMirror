<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Media\StorageQuotaService;
use Illuminate\Console\Command;

/**
 * `php artisan storage:recalculate {tenant?}` — re-derives every tenant's
 * `storage_bytes_used` from the real SUM of their `product_images
 * .size_bytes` rows, correcting any drift the running total accumulated.
 * Scheduled nightly (routes/console.php); see StorageQuotaService
 * ::recalculate()'s own docblock for why this exists instead of relying
 * on the increment/decrement running total alone.
 *
 * No TenantContext::runAs() wrapping needed here (unlike Decision D-26's
 * catalog:seed-defaults fix) — StorageQuotaService::recalculate() queries
 * ProductImage::withoutTenantScope() with an explicit tenant_id filter,
 * never relying on ambient context in the first place.
 */
class RecalculateStorageUsage extends Command
{
    protected $signature = 'storage:recalculate {tenant? : A single tenant ID; omit to recalculate every tenant}';

    protected $description = "Re-derive every tenant's storage_bytes_used from their actual product_images rows";

    public function handle(StorageQuotaService $quota): int
    {
        $tenantId = $this->argument('tenant');

        $tenants = $tenantId !== null
            ? Tenant::query()->where('id', (int) $tenantId)->get()
            : Tenant::query()->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching tenant found.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $total = $quota->recalculate($tenant);
            $this->info("Tenant #{$tenant->id}: {$total} bytes.");
        }

        return self::SUCCESS;
    }
}
