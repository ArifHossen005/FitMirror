<?php

use App\Http\Controllers\Api\V1\Auth\ActiveSessionController;
use App\Http\Controllers\Api\V1\Auth\ChangePasswordController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| Mounted at /api/v1 via the `apiPrefix` argument to withRouting() in
| bootstrap/app.php. This file holds tenant-facing routes only.
| Mission Control routes live in routes/api_mission.php (Phase 1.C),
| mounted separately at /api/v1/mission with their own guard.
|
*/

Route::get('/health', HealthController::class)
    ->name('api.v1.health');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('auth')->group(function () {
    Route::post('/register', RegisterController::class)
        ->middleware('throttle:auth')
        ->name('auth.register');

    Route::post('/login', LoginController::class)
        ->middleware('throttle:auth')
        ->name('auth.login');

    Route::post('/2fa/challenge', TwoFactorChallengeController::class)
        ->middleware('throttle:auth')
        ->name('auth.2fa.challenge');

    // Named `verification.verify` — Illuminate\Auth\Notifications\VerifyEmail
    // hardcodes this route name when building the signed link it emails out.
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('throttle:auth')
        ->name('verification.verify');

    Route::post('/forgot-password', ForgotPasswordController::class)
        ->middleware('throttle:auth')
        ->name('auth.forgot-password');

    Route::post('/reset-password', ResetPasswordController::class)
        ->middleware('throttle:auth')
        ->name('auth.reset-password');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', LogoutController::class)->name('auth.logout');
        Route::get('/me', MeController::class)->name('auth.me');
        Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:auth')
            ->name('auth.email.resend');
        Route::post('/change-password', ChangePasswordController::class)->name('auth.change-password');
        Route::patch('/profile', ProfileController::class)->name('auth.profile.update');

        Route::get('/sessions', [ActiveSessionController::class, 'index'])->name('auth.sessions.index');
        Route::delete('/sessions/{tokenId}', [ActiveSessionController::class, 'destroy'])
            ->whereNumber('tokenId')
            ->name('auth.sessions.destroy');

        Route::post('/2fa/enable', [TwoFactorController::class, 'enable'])->name('auth.2fa.enable');
        Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm'])->name('auth.2fa.confirm');
        Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->name('auth.2fa.disable');
        Route::post('/2fa/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])
            ->name('auth.2fa.recovery-codes');
    });
});
