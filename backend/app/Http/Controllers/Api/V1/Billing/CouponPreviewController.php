<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Enums\BillingCycle;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Billing\CouponPreviewRequest;
use App\Models\Plan;
use App\Services\Billing\CouponService;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/billing/coupon/preview — the checkout page's "apply coupon"
 * action. Stateless: validates the code against the chosen plan/cycle and
 * returns the computed discount without writing anything — nothing is
 * persisted until the coupon is actually used on a real invoice (see
 * CouponService's own docblock for why there's no separate "remove" API).
 */
class CouponPreviewController extends BaseApiController
{
    public function __invoke(CouponPreviewRequest $request, CouponService $coupons): JsonResponse
    {
        $user = $request->user();
        $plan = Plan::query()->findOrFail($request->validated('plan_id'));
        $cycle = BillingCycle::from($request->validated('billing_cycle'));
        $subtotal = $cycle === BillingCycle::Yearly ? $plan->price_yearly : $plan->price_monthly;

        $preview = $coupons->preview($request->validated('code'), $user->tenant, $plan, $subtotal);

        return $this->success([
            'code' => $preview['coupon']->code,
            'subtotal' => $subtotal,
            'discount' => $preview['discount'],
            'total_before_vat' => $subtotal - $preview['discount'],
        ]);
    }
}
