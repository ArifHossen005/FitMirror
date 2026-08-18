<?php

namespace App\Http\Controllers\Api\V1\Plan;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/plans — public, unauthenticated. The data source for Phase
 * 3.E's public pricing page: Plan::publicPlans() (is_public + Active,
 * ordered by sort_order — Free/Pro/Max), with limits/features eager
 * loaded so the comparison table never has to make a second round trip
 * per plan. Deliberately not hardcoded in the frontend — the product
 * document's own note that limits are Mission-Control-editable "without a
 * code change" (§1.5) only holds if the pricing page actually reads from
 * the same `plans`/`plan_limits`/`plan_features` rows every other gate in
 * the app reads from.
 */
class PlanListController extends BaseApiController
{
    public function __invoke(): JsonResponse
    {
        $plans = Plan::publicPlans()->load(['limits', 'features']);

        return $this->success($plans->map(fn (Plan $plan) => [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'price_monthly' => $plan->price_monthly,
            'price_yearly' => $plan->price_yearly,
            'currency' => $plan->currency,
            'trial_days' => $plan->trial_days,
            'limits' => $plan->limits->mapWithKeys(fn ($limit) => [$limit->key => $limit->value])->all(),
            'features' => $this->featuresFor($plan),
        ]));
    }

    /**
     * @return array<string, array{enabled: bool, tier: string|null}>
     */
    private function featuresFor(Plan $plan): array
    {
        $features = [];

        foreach ($plan->features as $feature) {
            $features[$feature->feature_key] = ['enabled' => $feature->enabled, 'tier' => $feature->tier()];
        }

        return $features;
    }
}
