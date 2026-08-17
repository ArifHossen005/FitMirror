<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant, in priority order:
 *   1. Custom domain — exact match against `tenants.custom_domain`
 *   2. Subdomain — `{slug}.{TENANT_ROOT_DOMAIN}`, production shape
 *   3. `X-Tenant` header carrying the slug directly — the local/staging
 *      stand-in every app's Axios client already sends (see
 *      packages/api/src/client.ts), since every local dev app runs on its
 *      own bare localhost port with no subdomain to resolve from
 *   4. The authenticated user's own `tenant_id` (Phase 2.B onward)
 *
 * Deliberately does not fail the request when no tenant resolves — many
 * routes (registration, Mission Control, the public health check) are not
 * tenant-scoped at all. EnsureTenantIsActive is the middleware that
 * enforces "this route requires a resolved, usable tenant".
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveFromCustomDomain($request)
            ?? $this->resolveFromSubdomain($request)
            ?? $this->resolveFromHeader($request)
            ?? $this->resolveFromAuthenticatedUser($request);

        if ($tenant !== null) {
            app(TenantContext::class)->set($tenant);
            $request->attributes->set('tenant_id', $tenant->id);
        }

        return $next($request);
    }

    private function resolveFromCustomDomain(Request $request): ?Tenant
    {
        return Tenant::query()
            ->where('custom_domain', $request->getHost())
            ->first();
    }

    private function resolveFromSubdomain(Request $request): ?Tenant
    {
        $rootDomain = config('app.tenant_root_domain');

        if (empty($rootDomain)) {
            return null;
        }

        $host = $request->getHost();

        if (!Str::endsWith($host, ".{$rootDomain}")) {
            return null;
        }

        $slug = Str::before($host, ".{$rootDomain}");

        if (empty($slug) || $slug === 'www') {
            return null;
        }

        return Tenant::query()->where('slug', $slug)->first();
    }

    private function resolveFromHeader(Request $request): ?Tenant
    {
        $slug = $request->header('X-Tenant');

        if (empty($slug)) {
            return null;
        }

        return Tenant::query()->where('slug', $slug)->first();
    }

    /**
     * Deliberately a direct PersonalAccessToken lookup, never
     * `$request->user()` or `Auth::guard('sanctum')->user()`. Two
     * independent problems ruled those out:
     *
     *   1. This middleware is prepended to the whole `api` group
     *      (bootstrap/app.php) and runs *before* any route's own
     *      `auth:sanctum` middleware. Bare `$request->user()` resolves
     *      against `config('auth.defaults.guard')` ('web', session-based),
     *      which is never authenticated on this API-only app — this
     *      fallback silently never fired until Phase 2.C's first route to
     *      depend on it (EnsureTenantIsActive) surfaced it as every staff/
     *      audit-log request 404ing with "Tenant not found" despite a
     *      valid Bearer token.
     *   2. `Auth::guard('sanctum')` looked like the fix, but Laravel's
     *      AuthManager caches guard instances for the container's lifetime
     *      (`$this->guards[$name] ??=`), and Sanctum's RequestGuard caches
     *      its resolved user on top of that. In a single test method that
     *      makes sequential requests as two different principals (e.g.
     *      Mission Control's ImpersonationTest — a SuperAdmin token, then
     *      the impersonated User's token), the second call's `user()`
     *      silently returned the *first* call's cached principal. A fresh,
     *      request-scoped token lookup sidesteps guard caching entirely —
     *      the same class of bug as Decision D-13's two login-time scope
     *      failures, caught the same way: a real feature test making real
     *      sequential requests, not inspection.
     */
    private function resolveFromAuthenticatedUser(Request $request): ?Tenant
    {
        $bearerToken = $request->bearerToken();

        if (empty($bearerToken)) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($bearerToken);

        if (!$accessToken || !$accessToken->tokenable instanceof User) {
            return null;
        }

        $tenantId = $accessToken->tokenable->getAttribute('tenant_id');

        if (empty($tenantId)) {
            return null;
        }

        return Tenant::query()->find($tenantId);
    }
}
