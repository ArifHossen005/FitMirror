<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Telescope is registered here — never in bootstrap/providers.php — so it
     * is structurally impossible for it to boot in production even if
     * TELESCOPE_ENABLED is left true by mistake in a production .env.
     */
    public function register(): void
    {
        if ($this->app->environment(['local', 'staging']) && config('telescope.enabled')) {
            $this->app->register(TelescopeServiceProvider::class);
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    /**
     * Named limiters used as route middleware, e.g. ->middleware('throttle:auth').
     * 'throttle' is Redis-backed automatically here because CACHE_STORE=redis
     * (see Middleware::throttleWithRedis() in the framework).
     */
    private function configureRateLimiters(): void
    {
        // General authenticated API traffic — generous, since a single kiosk
        // session or dashboard page can fire several requests in a burst.
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Login, register, password reset, OTP — brute-force protection.
        // Keyed by IP + the submitted identifier (email/phone) so one bad
        // actor can't lock out a legitimate user sharing their IP, while
        // still throttling repeated attempts against a single account.
        RateLimiter::for('auth', function ($request) {
            $identifier = (string) ($request->input('email') ?? $request->input('phone') ?? '');

            return Limit::perMinute(5)->by($request->ip() . '|' . $identifier);
        });

        // Per-tenant ceiling, independent of which user within the tenant is
        // calling. Falls back to the authenticated user's id, then IP, until
        // Phase 2.A's TenantContext service is in place to key by tenant_id
        // directly — this keeps the limiter usable as soon as it's attached
        // to a route, without depending on tenancy landing first.
        RateLimiter::for('tenant', function ($request) {
            $key = $request->attributes->get('tenant_id')
                ?? $request->user()?->tenant_id
                ?? $request->user()?->id
                ?? $request->ip();

            return Limit::perMinute(300)->by((string) $key);
        });

        // Kiosk devices authenticate with a long-lived device token
        // (Phase 4.A kiosk_devices.pairing_code / device token), not a user
        // session, so this is keyed by that token/IP rather than a user id.
        RateLimiter::for('kiosk', function ($request) {
            $key = $request->bearerToken() ?? $request->ip();

            return Limit::perMinute(60)->by($key);
        });
    }
}
