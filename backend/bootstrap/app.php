<?php

use App\Http\Middleware\AuthenticateKioskDevice;
use App\Http\Middleware\EnforcePlanFeature;
use App\Http\Middleware\EnsureKioskWithinActiveHours;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\ForgetStaleAuthGuards;
use App\Http\Middleware\ResolveLocale;
use App\Http\Middleware\ResolveTenant;
use App\Support\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api_v1.php',
        apiPrefix: 'api/v1',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
        then: function (): void {
            // withRouting() only accepts one `api:` file, so Mission
            // Control's routes (its own guard, its own middleware group —
            // see routes/api_mission.php) are registered here instead of
            // being merged into routes/api_v1.php. Passing through the
            // 'api' middleware group means it still picks up ResolveLocale
            // and the 'api' rate limiter configured below.
            Route::prefix('api/v1/mission')
                ->middleware('api')
                ->group(base_path('routes/api_mission.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Named limiters (api, auth, tenant, kiosk) are defined in
        // AppServiceProvider::boot(). 'api' applies to every request in the
        // api group automatically via throttleApi(); 'auth', 'tenant', and
        // 'kiosk' are attached per-route with ->middleware('throttle:auth').
        $middleware->throttleApi('api');

        // Order matters. ForgetStaleAuthGuards must run before anything
        // reads an authenticated principal — see its own docblock and
        // Decision D-20; ResolveTenant then resolves the tenant from this
        // request's own bearer token.
        $middleware->api(prepend: [
            ForgetStaleAuthGuards::class,
            ResolveLocale::class,
            ResolveTenant::class,
        ]);

        $middleware->alias([
            'super_admin' => EnsureSuperAdmin::class,
            'tenant.active' => EnsureTenantIsActive::class,
            'tenant.2fa' => EnsureTwoFactorIsEnabled::class,
            'plan.feature' => EnforcePlanFeature::class,
            // Kiosk devices authenticate by device token, not by user
            // session — see AuthenticateKioskDevice's docblock for why this
            // is not a Sanctum guard. 'kiosk.hours' must come after
            // 'kiosk.auth' in any route's stack; it reads the device that
            // middleware resolved.
            'kiosk.auth' => AuthenticateKioskDevice::class,
            'kiosk.hours' => EnsureKioskWithinActiveHours::class,
        ]);

        // FitMirror is API-only — there is no web 'login' route to redirect
        // an unauthenticated guest to. Without this, Authenticate::redirectTo()
        // throws RouteNotFoundException on any request that doesn't set an
        // explicit Accept: application/json header (e.g. a bare curl/browser
        // navigation), producing a 500 instead of the intended 401 JSON
        // response. Returning null forces the JSON path unconditionally.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiExceptionRenderer::render($e, $request);
            }
        });
    })->create();
