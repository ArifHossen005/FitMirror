<?php

namespace App\Http\Controllers\Api\V1\Subscription;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Subscription\CancelSubscriptionRequest;
use App\Services\Plan\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/subscription/cancel — only the tenant owner may cancel
 * (mirrors why only the owner can touch billing-adjacent settings
 * elsewhere, e.g. TenantPolicy::update() requiring 'tenant_settings.
 * update', which the seeded 'owner' role is the only one holding — see
 * config/permissions.php).
 */
class CancelSubscriptionController extends BaseApiController
{
    public function __invoke(CancelSubscriptionRequest $request, SubscriptionService $subscriptions): JsonResponse
    {
        $user = $request->user();

        if (!$user->isTenantOwner()) {
            return $this->error(
                trans('common.unauthorized'),
                Response::HTTP_FORBIDDEN,
                errorCode: 'unauthorized',
            );
        }

        $subscription = $subscriptions->currentFor($user->tenant);

        if (!$subscription) {
            return $this->error(
                'This tenant has no active subscription to cancel.',
                Response::HTTP_NOT_FOUND,
                errorCode: 'no_active_subscription',
            );
        }

        $subscription = $subscriptions->cancel(
            $subscription,
            $request->validated('reason'),
            (bool) $request->validated('immediately'),
        );

        return $this->success([
            'id' => $subscription->id,
            'status' => $subscription->status->value,
            'auto_renew' => $subscription->auto_renew,
            'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
        ], $subscription->status->value === 'cancelled'
            ? 'Subscription cancelled immediately.'
            : 'Subscription will not renew at the end of the current period.');
    }
}
