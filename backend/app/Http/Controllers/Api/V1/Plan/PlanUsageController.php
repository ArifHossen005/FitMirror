<?php

namespace App\Http\Controllers\Api\V1\Plan;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\User;
use App\Services\Plan\PlanService;
use App\Support\UsageCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/plan/usage — current usage vs. the tenant's resolved plan
 * limits, one row per key in config `plan_limits`. `current` is `null` for
 * a metric this phase has no real counter for yet (`categories`, `skus`,
 * `branches`, `storage_gb` — those land with Phase 5's catalog/media and
 * Phase 4's stores) rather than a misleading `0`, which would look like
 * "you have zero" instead of "not tracked yet".
 */
class PlanUsageController extends BaseApiController
{
    public function __invoke(Request $request, PlanService $plans, UsageCounter $usage): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $plan = $plans->resolve($tenant);

        $rows = $plan->limits->map(function ($limit) use ($tenant, $usage) {
            $current = match ($limit->key) {
                'try_on_sessions_per_day' => $usage->current($tenant, 'try_on_sessions_per_day'),
                'staff_accounts' => User::query()->where('tenant_id', $tenant->id)->count(),
                default => null,
            };

            return [
                'key' => $limit->key,
                'current' => $current,
                'limit' => $limit->value,
                'unlimited' => $limit->isUnlimited(),
            ];
        });

        return $this->success([
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
            ],
            'usage' => $rows,
        ]);
    }
}
