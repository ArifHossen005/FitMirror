<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Media\OrphanedMediaSweeper;
use Illuminate\Console\Command;

/**
 * `php artisan media:sweep-orphans {tenant?}` — deletes files on the
 * `tenant` disk that no `product_images` row references. Scheduled
 * weekly (routes/console.php); see OrphanedMediaSweeper's own docblock.
 */
class SweepOrphanedMedia extends Command
{
    protected $signature = 'media:sweep-orphans {tenant? : A single tenant ID; omit to sweep every tenant}';

    protected $description = 'Delete product image files on disk that no product_images row references';

    public function handle(OrphanedMediaSweeper $sweeper): int
    {
        $tenantId = $this->argument('tenant');

        $tenants = $tenantId !== null
            ? Tenant::query()->where('id', (int) $tenantId)->get()
            : Tenant::query()->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching tenant found.');

            return self::FAILURE;
        }

        $total = 0;

        foreach ($tenants as $tenant) {
            $deleted = $sweeper->sweep($tenant);
            $total += $deleted;
            $this->info("Tenant #{$tenant->id}: {$deleted} orphaned file(s) deleted.");
        }

        $this->info("Total: {$total} orphaned file(s) deleted.");

        return self::SUCCESS;
    }
}
