<?php

use App\Http\Controllers\Mission\ImpersonationController;
use App\Http\Controllers\Mission\ManualPaymentController;
use App\Http\Controllers\Mission\MissionAuthController;
use App\Http\Controllers\Mission\MissionHealthController;
use App\Http\Controllers\Mission\MissionProfileController;
use App\Http\Controllers\Mission\RefundController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mission Control API Routes
|--------------------------------------------------------------------------
|
| Mounted at /api/v1/mission via the `then` callback in bootstrap/app.php
| (Laravel's withRouting() only accepts a single `api:` file). Every
| authenticated route here uses the `super_admin` guard — configured in
| config/auth.php against the `super_admins` provider — never `sanctum`,
| so a tenant user's token can never reach Mission Control and a super
| admin's token can never reach the tenant API. See PROGRESS.md Phase 1.C.
|
*/

Route::get('/health', MissionHealthController::class)
    ->name('mission.health');

Route::post('/login', [MissionAuthController::class, 'login'])
    ->middleware('throttle:auth')
    ->name('mission.login');

Route::middleware(['auth:super_admin', 'super_admin'])->group(function () {
    Route::get('/me', MissionProfileController::class)
        ->name('mission.me');

    Route::post('/logout', [MissionAuthController::class, 'logout'])
        ->name('mission.logout');

    Route::post('/impersonate/{user}', [ImpersonationController::class, 'store'])
        ->whereNumber('user')
        ->name('mission.impersonate.start');

    Route::post('/tenants/{tenant}/payments', [ManualPaymentController::class, 'store'])
        ->whereNumber('tenant')
        ->name('mission.tenants.payments.store');

    Route::post('/payments/{payment}/refund', [RefundController::class, 'store'])
        ->whereNumber('payment')
        ->name('mission.payments.refund');
});
