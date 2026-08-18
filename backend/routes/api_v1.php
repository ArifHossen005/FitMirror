<?php

use App\Http\Controllers\Api\V1\Auth\ActiveSessionController;
use App\Http\Controllers\Api\V1\Auth\ChangePasswordController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\ImpersonationExitController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorController;
use App\Http\Controllers\Api\V1\Billing\AddonController;
use App\Http\Controllers\Api\V1\Billing\BillingHistoryController;
use App\Http\Controllers\Api\V1\Billing\CouponPreviewController;
use App\Http\Controllers\Api\V1\Billing\InvoiceController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Payment\PaymentCancelCallbackController;
use App\Http\Controllers\Api\V1\Payment\PaymentFailCallbackController;
use App\Http\Controllers\Api\V1\Payment\PaymentInitiateController;
use App\Http\Controllers\Api\V1\Payment\PaymentIpnController;
use App\Http\Controllers\Api\V1\Payment\PaymentSuccessCallbackController;
use App\Http\Controllers\Api\V1\Plan\PlanListController;
use App\Http\Controllers\Api\V1\Plan\PlanUsageController;
use App\Http\Controllers\Api\V1\Staff\AuditLogController;
use App\Http\Controllers\Api\V1\Staff\InviteAcceptController;
use App\Http\Controllers\Api\V1\Staff\StaffController;
use App\Http\Controllers\Api\V1\Staff\StaffInvitationController;
use App\Http\Controllers\Api\V1\Subscription\AutoRenewController;
use App\Http\Controllers\Api\V1\Subscription\CancelSubscriptionController;
use App\Http\Controllers\Api\V1\Subscription\CurrentSubscriptionController;
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

Route::get('/plans', PlanListController::class)->name('plans.index');

/*
|--------------------------------------------------------------------------
| SSLCommerz Callbacks & IPN (Phase 3.C)
|--------------------------------------------------------------------------
|
| Unauthenticated by design — SSLCommerz's servers/browser redirects carry
| no FitMirror session, only the posted tran_id/val_id fields. Each
| controller resolves its own Payment row via Payment::withoutTenantScope()
| rather than relying on ResolveTenant (which still runs as part of the
| global 'api' middleware group here, but finds no subdomain/header/user to
| resolve a tenant from on these routes and simply no-ops, per its own
| docblock).
|
*/
Route::prefix('payment')->group(function () {
    Route::post('/callback/success', PaymentSuccessCallbackController::class)->name('payment.callback.success');
    Route::post('/callback/fail', PaymentFailCallbackController::class)->name('payment.callback.fail');
    Route::post('/callback/cancel', PaymentCancelCallbackController::class)->name('payment.callback.cancel');
    Route::post('/ipn', PaymentIpnController::class)->name('payment.ipn');
});

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

    Route::post('/invitations/accept', InviteAcceptController::class)
        ->middleware('throttle:auth')
        ->name('auth.invitations.accept');

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

        Route::post('/impersonation/exit', ImpersonationExitController::class)
            ->name('auth.impersonation.exit');
    });
});

/*
|--------------------------------------------------------------------------
| Tenant Business Routes (Phase 2.C onward)
|--------------------------------------------------------------------------
|
| The first routes that need a *usable* tenant, not just a resolved one —
| 'tenant.active' and 'tenant.2fa' (defined but unattached since Phase 2.B,
| see their own docblocks) are wired in here for the first time.
|
*/
Route::middleware(['auth:sanctum', 'tenant.active', 'tenant.2fa'])->group(function () {
    Route::prefix('staff')->group(function () {
        Route::get('/', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/invitations', [StaffInvitationController::class, 'index'])->name('staff.invitations.index');
        Route::post('/invitations', [StaffInvitationController::class, 'store'])->name('staff.invitations.store');
        Route::delete('/invitations/{invitation}', [StaffInvitationController::class, 'destroy'])
            ->name('staff.invitations.destroy');
        Route::get('/{target}', [StaffController::class, 'show'])->whereNumber('target')->name('staff.show');
        Route::patch('/{target}/role', [StaffController::class, 'updateRole'])->whereNumber('target')->name('staff.role.update');
        Route::post('/{target}/deactivate', [StaffController::class, 'deactivate'])->whereNumber('target')->name('staff.deactivate');
        Route::post('/{target}/reactivate', [StaffController::class, 'reactivate'])->whereNumber('target')->name('staff.reactivate');
        Route::delete('/{target}', [StaffController::class, 'destroy'])->whereNumber('target')->name('staff.destroy');
    });

    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

    Route::get('/plan/usage', PlanUsageController::class)->name('plan.usage');

    Route::get('/subscription', CurrentSubscriptionController::class)->name('subscription.show');
    Route::post('/subscription/cancel', CancelSubscriptionController::class)->name('subscription.cancel');
    Route::patch('/subscription/auto-renew', AutoRenewController::class)->name('subscription.auto-renew');

    Route::prefix('billing')->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('billing.invoices.index');
        Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])
            ->name('billing.invoices.download');
        Route::get('/history', BillingHistoryController::class)->name('billing.history');
        Route::get('/addons', [AddonController::class, 'index'])->name('billing.addons.index');
        Route::post('/addons/{addon}/purchase', [AddonController::class, 'purchase'])
            ->name('billing.addons.purchase');
    });
});

/*
|--------------------------------------------------------------------------
| Payment Initiate (Phase 3.C)
|--------------------------------------------------------------------------
|
| Deliberately its own group, not folded into the 'tenant.active' group
| above — a tenant paying for the first time is, by definition, not yet
| active (see PaymentInitiateController's own docblock). Still behind
| 'tenant.2fa': the owner must already have finished 2FA setup to reach
| this route, same as every other business route.
|
*/
Route::middleware(['auth:sanctum', 'tenant.2fa'])->group(function () {
    Route::post('/payment/initiate', PaymentInitiateController::class)->name('payment.initiate');
    // Reachable pre-approval for the same reason as /payment/initiate above
    // — a coupon is applied *during* the first checkout, before the tenant
    // is active.
    Route::post('/billing/coupon/preview', CouponPreviewController::class)->name('billing.coupon.preview');
});
