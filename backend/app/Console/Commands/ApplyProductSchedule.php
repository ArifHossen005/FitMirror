<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Product\ProductScheduleService;
use Illuminate\Console\Command;

/**
 * `php artisan products:apply-schedule` — publishes products whose
 * `publish_at` has passed and unpublishes products whose `unpublish_at`
 * has passed, across every tenant. Scheduled daily (routes/console.php).
 */
class ApplyProductSchedule extends Command
{
    protected $signature = 'products:apply-schedule';

    protected $description = 'Publish/unpublish products whose publish_at/unpublish_at has passed';

    public function handle(ProductScheduleService $schedule): int
    {
        $totals = ['published' => 0, 'unpublished' => 0];

        foreach (Tenant::query()->cursor() as $tenant) {
            $result = $schedule->applyFor($tenant);
            $totals['published'] += $result['published'];
            $totals['unpublished'] += $result['unpublished'];
        }

        $this->info("Published {$totals['published']}, unpublished {$totals['unpublished']}.");

        return self::SUCCESS;
    }
}
