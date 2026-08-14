<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
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

    private function resolveFromAuthenticatedUser(Request $request): ?Tenant
    {
        $user = $request->user();
        $tenantId = $user?->getAttribute('tenant_id');

        if (empty($tenantId)) {
            return null;
        }

        return Tenant::query()->find($tenantId);
    }
}
