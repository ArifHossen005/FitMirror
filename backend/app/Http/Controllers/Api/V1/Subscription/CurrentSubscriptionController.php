<?php

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Services\Plan\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/subscription — the tenant's current trialing/active/past_due/
 * grace subscription (SubscriptionService::currentFor(), the same lookup
 * CancelSubscriptionController and AutoRenewController already use).
 * `data: null` when the tenant has none yet — a real, expected state for a
 * tenant that registered but hasn't been through checkout, not an error.
 */
class CurrentSubscriptionController extends BaseApiController
{
    public function __invoke(Request $request, SubscriptionService $subscriptions): JsonResponse
    {
        $subscription = $subscriptions->currentFor($request->user()->tenant);

        if (!$subscription) {
            return $this->success(null);
        }

        return $this->success([
            'id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'billing_cycle' => $subscription->billing_cycle->value,
            'status' => $subscription->status->value,
            'auto_renew' => $subscription->auto_renew,
            'starts_at' => $subscription->starts_at?->toIso8601String(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
            'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
        ]);
    }
}
