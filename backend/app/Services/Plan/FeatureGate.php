<?php

namespace App\Services\Plan;

use App\Models\FeatureFlag;
use App\Models\Tenant;

/**
 * `FeatureGate::allows($tenant, 'campaign_manager')` — the plan-entitlement
 * half of feature gating. `FeatureFlag::isEnabled()` (platform-wide kill
 * switches) is the other, orthogonal half; a route can depend on both
 * (the flag must be on *and* the tenant's plan must include it).
 */
class FeatureGate
{
    public function __construct(private readonly PlanService $plans) {}

    public function allows(Tenant $tenant, string $featureKey): bool
    {
        $plan = $this->plans->resolve($tenant);

        return (bool) $plan->features->firstWhere('feature_key', $featureKey)?->enabled;
    }

    /**
     * The tier detail for a feature that isn't simple on/off (e.g.
     * `analytics` => 'basic'/'advanced'/'full_ai') — null if the tenant's
     * plan doesn't have the feature at all, or the feature has no tier
     * concept.
     */
    public function tier(Tenant $tenant, string $featureKey): ?string
    {
        $plan = $this->plans->resolve($tenant);

        return $plan->features->firstWhere('feature_key', $featureKey)?->tier();
    }

    public function flagEnabled(string $flagKey): bool
    {
        return FeatureFlag::isEnabled($flagKey);
    }
}
