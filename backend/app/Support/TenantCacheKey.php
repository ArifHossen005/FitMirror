<?php

namespace App\Support;

/**
 * Prefixes a cache key with the active tenant so two tenants can never
 * collide on the same logical key (e.g. "product:42" meaning a different
 * product per tenant). Falls back to a "global:" prefix when no tenant
 * context is active, so platform-wide cache entries (plan definitions,
 * feature flags) stay distinguishable from tenant-scoped ones in the same
 * Redis keyspace.
 */
class TenantCacheKey
{
    public static function make(string $key): string
    {
        $context = app(TenantContext::class);

        $scope = $context->has() ? "tenant:{$context->id()}" : 'global';

        return "{$scope}:{$key}";
    }
}
