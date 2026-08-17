<?php

namespace App\Console\Commands;

use App\Support\UsageCounter;
use Illuminate\Console\Command;

/**
 * `php artisan usage:reset` — deletes every tenant's Redis usage counter
 * (try-on sessions/day, SMS sent/day, etc.), scheduled daily at midnight
 * Asia/Dhaka (routes/console.php). A single global reset for every tenant
 * at once, not a per-tenant midnight, mirrors how the product itself talks
 * about the limit ("৫০ Try-on Session/দিন") — one shared business day, not
 * a follow-the-sun rolling window per tenant.
 */
class ResetUsageCounters extends Command
{
    protected $signature = 'usage:reset';

    protected $description = "Reset every tenant's daily Redis usage counters (try-on sessions, SMS, etc.)";

    public function handle(UsageCounter $counter): int
    {
        $deleted = $counter->resetAll();

        $this->info("Reset {$deleted} usage counter(s).");

        return self::SUCCESS;
    }
}
