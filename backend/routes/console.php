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
