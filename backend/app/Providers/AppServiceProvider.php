<?php

namespace App\Providers;

use App\Auth\TenantUnawareUserProvider;
use App\Models\PersonalAccessToken;
use App\Support\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;
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

        // Singleton, not a fresh instance per resolution — ResolveTenant
        // middleware sets it once per request and every BelongsToTenant
        // query (TenantScope) must see that same instance. See
        // TenantContext's own docblock for the queue-worker caveat.
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiters();
        $this->configurePasswordPolicy();
        $this->configurePasswordResetUrl();

        // See App\Models\PersonalAccessToken's docblock — without this,
        // every auth:sanctum request against a tenant User fails to
        // resolve because TenantScope fails closed and no tenant context
        // exists yet at the point a token is being resolved.
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // See App\Auth\TenantUnawareUserProvider's docblock — the `users`
        // provider (config/auth.php) uses this driver instead of the
        // default `eloquent` one, so the password reset broker can find a
        // User by email before any tenant context exists.
        Auth::provider('tenant_unaware_eloquent', function ($app, array $config) {
            return new TenantUnawareUserProvider($app['hash'], $config['model']);
        });
    }

    /**
     * The single password policy every `password` => ['required',
     * Password::defaults()] validation rule in the app inherits — see
     * PROGRESS.md Phase 2.B "Implement password policy". `uncompromised()`
     * checks the password against the Have I Been Pwned k-anonymity API on
     * every validation; tests fake that HTTP call (see
     * tests/Feature/Auth/RegisterTest.php) rather than hitting the network.
     */
    private function configurePasswordPolicy(): void
    {
        Password::defaults(fn () => Password::min(10)
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised());
    }

    /**
     * Without this, Illuminate\Auth\Notifications\ResetPassword's default
     * URL builder calls route('password.reset', ...) — a *web* route this
     * API-only app never registers — and falls back to a bare backend
     * APP_URL the dashboard SPA can't render. Points at
     * FRONTEND_URL/reset-password instead, read by ResetPasswordPage via
     * ?token=&email= query params, mirroring
     * StaffInvitationNotification's identical reasoning for invite links.
     */
    private function configurePasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $email = method_exists($notifiable, 'getEmailForPasswordReset')
                ? $notifiable->getEmailForPasswordReset()
                : $notifiable->email;

            return rtrim(config('app.frontend_url'), '/')
                . '/reset-password?token=' . $token
                . '&email=' . urlencode($email);
        });
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
