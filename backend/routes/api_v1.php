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
use App\Http\Controllers\Api\V1\Catalog\AttributeController;
use App\Http\Controllers\Api\V1\Catalog\AttributeValueController;
use App\Http\Controllers\Api\V1\Catalog\CategoryController;
use App\Http\Controllers\Api\V1\Catalog\OccasionController;
use App\Http\Controllers\Api\V1\Catalog\TagController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Inventory\InventoryReportController;
use App\Http\Controllers\Api\V1\Inventory\StockController;
use App\Http\Controllers\Api\V1\Kiosk\KioskDeviceController;
use App\Http\Controllers\Api\V1\Kiosk\KioskSessionController;
use App\Http\Controllers\Api\V1\Kiosk\SizeChartController as KioskSizeChartController;
use App\Http\Controllers\Api\V1\Media\PresignedUploadController;
use App\Http\Controllers\Api\V1\Payment\PaymentCancelCallbackController;
use App\Http\Controllers\Api\V1\Payment\PaymentFailCallbackController;
use App\Http\Controllers\Api\V1\Payment\PaymentInitiateController;
use App\Http\Controllers\Api\V1\Payment\PaymentIpnController;
use App\Http\Controllers\Api\V1\Payment\PaymentSuccessCallbackController;
use App\Http\Controllers\Api\V1\Plan\PlanListController;
use App\Http\Controllers\Api\V1\Plan\PlanUsageController;
use App\Http\Controllers\Api\V1\Product\PriceHistoryController;
use App\Http\Controllers\Api\V1\Product\ProductController;
use App\Http\Controllers\Api\V1\Product\ProductImageController;
use App\Http\Controllers\Api\V1\Product\SizeChartController;
use App\Http\Controllers\Api\V1\Staff\AuditLogController;
use App\Http\Controllers\Api\V1\Staff\InviteAcceptController;
use App\Http\Controllers\Api\V1\Staff\StaffController;
use App\Http\Controllers\Api\V1\Staff\StaffInvitationController;
use App\Http\Controllers\Api\V1\Store\FranchiseGroupController;
use App\Http\Controllers\Api\V1\Store\ShiftController;
use App\Http\Controllers\Api\V1\Store\StoreController;
use App\Http\Controllers\Api\V1\Store\StoreHoursController;
use App\Http\Controllers\Api\V1\Subscription\AutoRenewController;
use App\Http\Controllers\Api\V1\Subscription\CancelSubscriptionController;
use App\Http\Controllers\Api\V1\Subscription\CurrentSubscriptionController;
use App\Http\Controllers\Api\V1\Tenant\CustomDomainController;
use App\Http\Controllers\Api\V1\Tenant\SubdomainController;
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

/*
|--------------------------------------------------------------------------
| Store, Branch & Staff Management (Phase 4.A)
|--------------------------------------------------------------------------
|
| Same guard stack as the tenant business routes above — an authenticated
| user, an active tenant, and (for the literal owner) 2FA. Kept in its own
| group purely for readability; there is no middleware difference.
|
| The franchise and custom-domain routes add `plan.feature:*` on top, which
| is the first time EnforcePlanFeature is attached to a real route since it
| was built ahead of demand in Phase 3.A.
|
*/
Route::middleware(['auth:sanctum', 'tenant.active', 'tenant.2fa'])->group(function () {
    Route::prefix('stores')->group(function () {
        Route::get('/', [StoreController::class, 'index'])->name('stores.index');
        Route::post('/', [StoreController::class, 'store'])->name('stores.store');
        Route::get('/{store}', [StoreController::class, 'show'])->whereNumber('store')->name('stores.show');
        Route::patch('/{store}', [StoreController::class, 'update'])->whereNumber('store')->name('stores.update');
        // POST, not PATCH: PHP only parses a multipart body on POST, so a
        // multipart PATCH arrives with no uploaded files at all. See
        // StoreBrandingRequest's own docblock.
        Route::post('/{store}/branding', [StoreController::class, 'branding'])
            ->whereNumber('store')
            ->name('stores.branding');
        Route::delete('/{store}', [StoreController::class, 'destroy'])->whereNumber('store')->name('stores.destroy');

        Route::get('/{store}/hours', [StoreHoursController::class, 'show'])
            ->whereNumber('store')
            ->name('stores.hours.show');
        Route::put('/{store}/hours', [StoreHoursController::class, 'update'])
            ->whereNumber('store')
            ->name('stores.hours.update');

        Route::get('/{store}/kiosk-devices', [KioskDeviceController::class, 'index'])
            ->whereNumber('store')
            ->name('stores.kiosk-devices.index');
        Route::post('/{store}/kiosk-devices', [KioskDeviceController::class, 'store'])
            ->whereNumber('store')
            ->name('stores.kiosk-devices.store');

        Route::post('/{store}/shifts', [ShiftController::class, 'store'])
            ->whereNumber('store')
            ->name('stores.shifts.store');
    });

    Route::prefix('kiosk-devices')->group(function () {
        Route::get('/{device}', [KioskDeviceController::class, 'show'])
            ->whereNumber('device')
            ->name('kiosk-devices.show');
        Route::post('/{device}/pairing-code', [KioskDeviceController::class, 'pairingCode'])
            ->whereNumber('device')
            ->name('kiosk-devices.pairing-code');
        Route::post('/{device}/unpair', [KioskDeviceController::class, 'unpair'])
            ->whereNumber('device')
            ->name('kiosk-devices.unpair');
        Route::post('/{device}/suspend', [KioskDeviceController::class, 'suspend'])
            ->whereNumber('device')
            ->name('kiosk-devices.suspend');
        Route::post('/{device}/reactivate', [KioskDeviceController::class, 'reactivate'])
            ->whereNumber('device')
            ->name('kiosk-devices.reactivate');
        Route::get('/{device}/settings', [KioskDeviceController::class, 'settings'])
            ->whereNumber('device')
            ->name('kiosk-devices.settings.show');
        Route::put('/{device}/settings', [KioskDeviceController::class, 'updateSettings'])
            ->whereNumber('device')
            ->name('kiosk-devices.settings.update');
        Route::delete('/{device}', [KioskDeviceController::class, 'destroy'])
            ->whereNumber('device')
            ->name('kiosk-devices.destroy');
    });

    Route::prefix('shifts')->group(function () {
        Route::get('/schedule', [ShiftController::class, 'schedule'])->name('shifts.schedule');
        Route::patch('/{shift}', [ShiftController::class, 'update'])->whereNumber('shift')->name('shifts.update');
        Route::post('/{shift}/cancel', [ShiftController::class, 'cancel'])
            ->whereNumber('shift')
            ->name('shifts.cancel');
        Route::delete('/{shift}', [ShiftController::class, 'destroy'])
            ->whereNumber('shift')
            ->name('shifts.destroy');
    });

    Route::prefix('tenant')->group(function () {
        Route::get('/subdomain', [SubdomainController::class, 'show'])->name('tenant.subdomain.show');
        Route::get('/subdomain/check', [SubdomainController::class, 'check'])
            ->middleware('throttle:tenant')
            ->name('tenant.subdomain.check');
        Route::post('/subdomain', [SubdomainController::class, 'assign'])->name('tenant.subdomain.assign');

        Route::middleware('plan.feature:custom_domain')->group(function () {
            Route::get('/custom-domain', [CustomDomainController::class, 'show'])
                ->name('tenant.custom-domain.show');
            Route::post('/custom-domain', [CustomDomainController::class, 'store'])
                ->name('tenant.custom-domain.store');
            Route::post('/custom-domain/verify', [CustomDomainController::class, 'verify'])
                ->name('tenant.custom-domain.verify');
            Route::delete('/custom-domain', [CustomDomainController::class, 'destroy'])
                ->name('tenant.custom-domain.destroy');
        });
    });

    Route::middleware('plan.feature:franchise_management')
        ->prefix('franchise-groups')
        ->group(function () {
            Route::get('/', [FranchiseGroupController::class, 'index'])->name('franchise-groups.index');
            Route::post('/', [FranchiseGroupController::class, 'store'])->name('franchise-groups.store');
            Route::get('/{group}/overview', [FranchiseGroupController::class, 'overview'])
                ->whereNumber('group')
                ->name('franchise-groups.overview');
            Route::post('/{group}/members', [FranchiseGroupController::class, 'addMember'])
                ->whereNumber('group')
                ->name('franchise-groups.members.store');
            Route::delete('/{group}/members/{memberTenantId}', [FranchiseGroupController::class, 'removeMember'])
                ->whereNumber('group')
                ->whereNumber('memberTenantId')
                ->name('franchise-groups.members.destroy');
            Route::delete('/{group}', [FranchiseGroupController::class, 'destroy'])
                ->whereNumber('group')
                ->name('franchise-groups.destroy');
        });
});

/*
|--------------------------------------------------------------------------
| Product & Catalog Management (Phase 5.A / 5.B)
|--------------------------------------------------------------------------
|
| Same guard stack as every other tenant business route group. The
| `/categories/reorder` route is registered before `/categories/{category}`
| so "reorder" is never captured by the {category} parameter — the
| whereNumber('category') constraint on that route would already prevent a
| wrong match, but registration order is kept correct regardless since
| that's the convention Laravel route matching relies on.
|
*/
Route::middleware(['auth:sanctum', 'tenant.active', 'tenant.2fa'])->group(function () {
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/', [CategoryController::class, 'store'])->name('categories.store');
        Route::patch('/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
        Route::get('/{category}', [CategoryController::class, 'show'])->whereNumber('category')->name('categories.show');
        Route::patch('/{category}', [CategoryController::class, 'update'])->whereNumber('category')->name('categories.update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->whereNumber('category')->name('categories.destroy');
    });

    Route::prefix('attributes')->group(function () {
        Route::get('/', [AttributeController::class, 'index'])->name('attributes.index');
        Route::post('/', [AttributeController::class, 'store'])->name('attributes.store');
        Route::get('/{attribute}', [AttributeController::class, 'show'])->whereNumber('attribute')->name('attributes.show');
        Route::patch('/{attribute}', [AttributeController::class, 'update'])->whereNumber('attribute')->name('attributes.update');
        Route::delete('/{attribute}', [AttributeController::class, 'destroy'])->whereNumber('attribute')->name('attributes.destroy');

        Route::post('/{attribute}/values', [AttributeValueController::class, 'store'])
            ->whereNumber('attribute')
            ->name('attributes.values.store');
        Route::patch('/{attribute}/values/{value}', [AttributeValueController::class, 'update'])
            ->whereNumber(['attribute', 'value'])
            ->name('attributes.values.update');
        Route::delete('/{attribute}/values/{value}', [AttributeValueController::class, 'destroy'])
            ->whereNumber(['attribute', 'value'])
            ->name('attributes.values.destroy');
    });

    Route::prefix('occasions')->group(function () {
        Route::get('/', [OccasionController::class, 'index'])->name('occasions.index');
        Route::post('/', [OccasionController::class, 'store'])->name('occasions.store');
        Route::patch('/{occasion}', [OccasionController::class, 'update'])->whereNumber('occasion')->name('occasions.update');
        Route::delete('/{occasion}', [OccasionController::class, 'destroy'])->whereNumber('occasion')->name('occasions.destroy');
    });

    Route::prefix('tags')->group(function () {
        Route::get('/', [TagController::class, 'index'])->name('tags.index');
        Route::post('/', [TagController::class, 'store'])->name('tags.store');
        Route::patch('/{tag}', [TagController::class, 'update'])->whereNumber('tag')->name('tags.update');
        Route::delete('/{tag}', [TagController::class, 'destroy'])->whereNumber('tag')->name('tags.destroy');
    });

    Route::prefix('size-charts')->group(function () {
        Route::get('/', [SizeChartController::class, 'index'])->name('size-charts.index');
        Route::post('/', [SizeChartController::class, 'store'])->name('size-charts.store');
        Route::get('/{sizeChart}', [SizeChartController::class, 'show'])->whereNumber('sizeChart')->name('size-charts.show');
        Route::patch('/{sizeChart}', [SizeChartController::class, 'update'])->whereNumber('sizeChart')->name('size-charts.update');
        Route::delete('/{sizeChart}', [SizeChartController::class, 'destroy'])->whereNumber('sizeChart')->name('size-charts.destroy');
    });

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('products.index');
        Route::post('/', [ProductController::class, 'store'])->name('products.store');
        Route::get('/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');
        Route::patch('/{product}', [ProductController::class, 'update'])->whereNumber('product')->name('products.update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->whereNumber('product')->name('products.destroy');
        Route::post('/{product}/duplicate', [ProductController::class, 'duplicate'])
            ->whereNumber('product')
            ->name('products.duplicate');
        Route::post('/{product}/publish', [ProductController::class, 'publish'])
            ->whereNumber('product')
            ->name('products.publish');
        Route::post('/{product}/unpublish', [ProductController::class, 'unpublish'])
            ->whereNumber('product')
            ->name('products.unpublish');
        Route::put('/{product}/tags', [ProductController::class, 'syncTags'])
            ->whereNumber('product')
            ->name('products.tags.sync');
        Route::put('/{product}/occasions', [ProductController::class, 'syncOccasions'])
            ->whereNumber('product')
            ->name('products.occasions.sync');
        Route::put('/{product}/size-charts', [SizeChartController::class, 'sync'])
            ->whereNumber('product')
            ->name('products.size-charts.sync');

        Route::post('/{product}/images', [ProductImageController::class, 'store'])
            ->whereNumber('product')
            ->name('products.images.store');
        Route::patch('/{product}/images/reorder', [ProductImageController::class, 'reorder'])
            ->whereNumber('product')
            ->name('products.images.reorder');
        Route::delete('/{product}/images/{image}', [ProductImageController::class, 'destroy'])
            ->whereNumber(['product', 'image'])
            ->name('products.images.destroy');
        Route::post('/{product}/images/{image}/remove-background', [ProductImageController::class, 'removeBackground'])
            ->whereNumber(['product', 'image'])
            ->name('products.images.remove-background');
        Route::post('/{product}/tryon-asset', [ProductImageController::class, 'uploadTryonAsset'])
            ->whereNumber('product')
            ->name('products.tryon-asset.store');

        Route::get('/{product}/price-history', [PriceHistoryController::class, 'index'])
            ->whereNumber('product')
            ->name('products.price-history.index');
    });

    Route::post('/media/presigned-upload', [PresignedUploadController::class, 'store'])
        ->name('media.presigned-upload');

    Route::prefix('variants')->group(function () {
        Route::get('/{variant}/stock', [StockController::class, 'show'])
            ->whereNumber('variant')
            ->name('variants.stock.show');
        Route::post('/{variant}/stock/adjust', [StockController::class, 'adjust'])
            ->whereNumber('variant')
            ->name('variants.stock.adjust');
        Route::post('/{variant}/stock/transfer', [StockController::class, 'transfer'])
            ->whereNumber('variant')
            ->name('variants.stock.transfer');
        Route::get('/{variant}/stock/movements', [StockController::class, 'movements'])
            ->whereNumber('variant')
            ->name('variants.stock.movements');
        Route::patch('/{variant}/low-stock-threshold', [StockController::class, 'updateLowStockThreshold'])
            ->whereNumber('variant')
            ->name('variants.low-stock-threshold.update');
    });

    Route::get('/inventory/report', [InventoryReportController::class, 'report'])->name('inventory.report');
});

/*
|--------------------------------------------------------------------------
| Kiosk Device API (Phase 4.A)
|--------------------------------------------------------------------------
|
| Authenticated by device token, not by a user session — see
| App\Http\Middleware\AuthenticateKioskDevice for why this is deliberately
| not Sanctum. Rate-limited by the 'kiosk' limiter defined back in Phase
| 1.A (AppServiceProvider::configureRateLimiters()), which keys on the
| bearer token rather than a user id; this is the first route group to
| actually use it.
|
| /kiosk/claim is the only unauthenticated route here — it is how a device
| obtains its token in the first place. It carries the stricter 'auth'
| limiter on top, since an unauthenticated endpoint that resolves a short
| code is exactly the shape that needs brute-force protection.
|
*/
Route::prefix('kiosk')->middleware('throttle:kiosk')->group(function () {
    Route::post('/claim', [KioskSessionController::class, 'claim'])
        ->middleware('throttle:auth')
        ->name('kiosk.claim');

    Route::middleware('kiosk.auth')->group(function () {
        Route::post('/heartbeat', [KioskSessionController::class, 'heartbeat'])->name('kiosk.heartbeat');
        Route::get('/config', [KioskSessionController::class, 'config'])->name('kiosk.config');
        Route::get('/availability', [KioskSessionController::class, 'availability'])->name('kiosk.availability');

        // Phase 5.B — the kiosk popup payload for a product's attached size
        // charts. See Api\V1\Kiosk\SizeChartController's own docblock.
        Route::get('/products/{product}/size-charts', [KioskSizeChartController::class, 'show'])
            ->whereNumber('product')
            ->name('kiosk.products.size-charts');

        // The one kiosk route behind the active-hours guard — see
        // EnsureKioskWithinActiveHours for why the others deliberately are
        // not.
        Route::post('/sessions/authorize', [KioskSessionController::class, 'authorizeSession'])
            ->middleware('kiosk.hours')
            ->name('kiosk.sessions.authorize');
    });
});
