<?php

namespace App\Console\Commands;

use App\Jobs\NotifyLowStockJob;
use App\Models\Tenant;
use App\Services\Inventory\LowStockDetectionService;
use Illuminate\Console\Command;

/**
 * `php artisan inventory:check-low-stock` — the low-stock detection job
 * PROGRESS.md's 5.D checklist asks for. Scheduled daily (routes/console.php);
 * dispatches one digest email per tenant that has any variant at or below
 * its threshold, never one email per variant (NotifyLowStockJob's own
 * docblock).
 */
class CheckLowStock extends Command
{
    protected $signature = 'inventory:check-low-stock';

    protected $description = 'Alert tenants whose product variants are at or below their low-stock threshold';

    public function handle(LowStockDetectionService $detector): int
    {
        $notified = 0;

        foreach (Tenant::query()->cursor() as $tenant) {
            $variants = $detector->detectFor($tenant);

            if ($variants->isEmpty()) {
                continue;
            }

            NotifyLowStockJob::dispatch($tenant->id, $variants->pluck('id')->all());
            $notified++;
        }

        $this->info("Queued low-stock alerts for {$notified} tenant(s).");

        return self::SUCCESS;
    }
}
