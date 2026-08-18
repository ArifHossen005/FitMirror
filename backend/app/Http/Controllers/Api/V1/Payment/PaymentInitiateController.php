<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Enums\BillingCycle;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Payment\PaymentInitiateRequest;
use App\Models\Plan;
use App\Services\Billing\PaymentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/payment/initiate — owner-only, same reasoning as
 * CancelSubscriptionController (this is a billing action). Deliberately
 * NOT behind 'tenant.active': a tenant that hasn't paid yet is, by
 * definition, not active (see TenantStatus::Pending's label, "Pending
 * Approval") — gating payment behind an active tenant would make it
 * impossible to ever pay. It IS behind 'tenant.2fa' (see routes/api_v1.php)
 * since the owner must already have finished 2FA setup to reach any
 * business route, and this is one.
 */
class PaymentInitiateController extends BaseApiController
{
    public function __invoke(PaymentInitiateRequest $request, PaymentService $payments): JsonResponse
    {
        $user = $request->user();

        if (!$user->isTenantOwner()) {
            return $this->error(
                trans('common.unauthorized'),
                Response::HTTP_FORBIDDEN,
                errorCode: 'unauthorized',
            );
        }

        $plan = Plan::query()->findOrFail($request->validated('plan_id'));
        $cycle = BillingCycle::from($request->validated('billing_cycle'));

        $result = $payments->initiate($user->tenant, $user, $plan, $cycle, $request->validated('coupon_code'));

        return $this->success([
            'invoice_number' => $result['invoice']->number,
            'subtotal' => $result['invoice']->subtotal,
            'discount' => $result['invoice']->discount,
            'vat' => $result['invoice']->vat,
            'amount' => $result['payment']->amount,
            'currency' => $result['payment']->currency,
            'gateway_url' => $result['gateway_url'],
        ], 'Redirect the customer to gateway_url to complete payment.');
    }
}
