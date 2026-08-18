<?php

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Subscription\UpdateAutoRenewRequest;
use App\Services\Plan\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * PATCH /api/v1/subscription/auto-renew — owner-only, same reasoning as
 * CancelSubscriptionController. "Payment method" itself has no toggle
 * here: SSLCommerz's hosted checkout page is where a customer picks
 * card/bKash/Nagad/etc. on every payment, this integration never stores a
 * preferred method, so there is nothing to persist for the other half of
 * the Phase 3.E "payment method / auto-renew toggle" checklist item.
 */
class AutoRenewController extends BaseApiController
{
    public function __invoke(UpdateAutoRenewRequest $request, SubscriptionService $subscriptions): JsonResponse
    {
        $user = $request->user();

        if (!$user->isTenantOwner()) {
            return $this->error(trans('common.unauthorized'), Response::HTTP_FORBIDDEN, errorCode: 'unauthorized');
        }

        $subscription = $subscriptions->currentFor($user->tenant);

        if (!$subscription) {
            return $this->error(
                'This tenant has no active subscription.',
                Response::HTTP_NOT_FOUND,
                errorCode: 'no_active_subscription',
            );
        }

        $subscription = $subscriptions->setAutoRenew($subscription, (bool) $request->validated('auto_renew'));

        return $this->success(['id' => $subscription->id, 'auto_renew' => $subscription->auto_renew]);
    }
}
