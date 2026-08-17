<?php

namespace App\Services\Plan;

use App\Exceptions\PlanLimitExceededException;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\BaseService;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves "what does this tenant's plan actually allow" — the single
 * place every limit/feature check goes through, so a tenant with no
 * `plan_id` yet (registered but hasn't been through checkout — Phase 3.E
 * doesn't exist) gets a real, safe answer instead of every caller having
 * to null-check `$tenant->plan` itself.
 */
class PlanService extends BaseService
{
    private const CACHE_TTL_MINUTES = 10;

    /**
     * The tenant's own plan, or the Free plan as the floor for a tenant
     * that hasn't chosen one yet. Cached briefly per tenant — this is
     * consulted on the hot path of every plan-gated request.
     */
    public function resolve(Tenant $tenant): Plan
    {
        return Cache::remember(
            $this->cacheKey($tenant),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($tenant) {
                $plan = $tenant->plan_id ? $tenant->plan()->with(['limits', 'features'])->first() : null;

                return ($plan && $plan->isUsable()) ? $plan : Plan::free()->load(['limits', 'features']);
            },
        );
    }

    /**
     * Null means unlimited — see PlanLimit's own docblock. A key with no
     * row for the resolved plan is treated the same way (never a silent
     * zero-cap).
     */
    public function limit(Tenant $tenant, string $key): ?int
    {
        $plan = $this->resolve($tenant);

        $limit = $plan->limits->firstWhere('key', $key);

        return $limit?->value;
    }

    public function withinLimit(Tenant $tenant, string $key, int $currentCount): bool
    {
        $limit = $this->limit($tenant, $key);

        return $limit === null || $currentCount < $limit;
    }

    /**
     * The "EnforcePlanLimit action" side of Phase 3.A — deliberately a
     * plain method services call at the point they're about to create the
     * (current-count + 1)th resource, not route middleware. A generic
     * middleware can't know "how many categories does this tenant already
     * have" without being told which countable resource the route even
     * concerns, so each caller (StaffInvitationService today; Product/
     * Category services from Phase 5 onward) passes its own current count.
     *
     * @throws PlanLimitExceededException
     */
    public function assertWithinLimit(Tenant $tenant, string $key, int $currentCount): void
    {
        $limit = $this->limit($tenant, $key);

        if ($limit !== null && $currentCount >= $limit) {
            throw new PlanLimitExceededException($key, $currentCount, $limit);
        }
    }

    /**
     * Forgets the cached resolution — call after a tenant's plan changes
     * (upgrade/downgrade, Phase 3.B) so the new limits/features apply
     * immediately instead of waiting out the cache TTL.
     */
    public function forget(Tenant $tenant): void
    {
        Cache::forget($this->cacheKey($tenant));
    }

    /**
     * Deliberately not App\Support\TenantCacheKey — that prefixes by the
     * *active ambient* TenantContext, which may not be $tenant (e.g. a
     * scheduled job iterating every tenant in turn). $tenant->id is
     * already embedded explicitly, so no ambient context is needed.
     */
    private function cacheKey(Tenant $tenant): string
    {
        return "plan:resolved:{$tenant->id}";
    }
}
