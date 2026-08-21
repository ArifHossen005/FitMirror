<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Asia/Dhaka, not UTC (storage stays UTC per Decision D-07) — daily plan
// limits are a business-day concept ("৫০ Try-on Session/দিন" per the
// product document), and FitMirror's whole target market is Bangladesh.
Schedule::command('usage:reset')->dailyAt('00:00')->timezone('Asia/Dhaka');

// Phase 5.C — corrects any drift in the running storage_bytes_used total
// (StorageQuotaService's own docblock) and clears out files nothing
// references any more. Both are self-healing backstops, not the primary
// path (increment/decrement on upload/delete and the delete API's own
// cleanup are), so nightly/weekly is generous rather than urgent.
Schedule::command('storage:recalculate')->dailyAt('01:00')->timezone('Asia/Dhaka');
Schedule::command('media:sweep-orphans')->weeklyOn(1, '02:00')->timezone('Asia/Dhaka');

// Phase 5.D — applies publish_at/unpublish_at scheduling and flags
// low-stock variants. Business-day concept, same Asia/Dhaka reasoning as
// usage:reset above.
Schedule::command('products:apply-schedule')->dailyAt('00:30')->timezone('Asia/Dhaka');
Schedule::command('inventory:check-low-stock')->dailyAt('08:00')->timezone('Asia/Dhaka');
