<?php

namespace App\Services\Billing;

use App\Enums\CouponStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\BaseService;
use Illuminate\Validation\ValidationException;

/**
 * Validation and discount calculation only — no persisted "cart" or
 * "apply/remove" state exists anywhere in this app (see PROGRESS.md Phase
 * 3.D's own notes), so "apply" a coupon just means the checkout page holds
 * the code in local state and calls preview() again on every change;
 * "remove" is simply not sending a code on the next call. Nothing is
 * written to the database until redeem() is called, which only ever
 * happens once — when an Invoice is actually created (PaymentService::
 * resolvePendingInvoice()) — never on every preview keystroke.
 */
class CouponService extends BaseService
{
    /**
     * @return array{coupon: ?Coupon, discount: int}
     *
     * @throws ValidationException
     */
    public function preview(?string $code, Tenant $tenant, Plan $plan, int $subtotal): array
    {
        if ($code === null || trim($code) === '') {
            return ['coupon' => null, 'discount' => 0];
        }

        $coupon = Coupon::query()->where('code', $code)->first();

        if (!$coupon || $coupon->status !== CouponStatus::Active) {
            throw ValidationException::withMessages(['coupon' => ['This coupon code is not valid.']]);
        }

        if (!$coupon->isWithinWindow()) {
            throw ValidationException::withMessages(['coupon' => ['This coupon has expired or is not active yet.']]);
        }

        if (!$coupon->appliesToPlan($plan)) {
            throw ValidationException::withMessages(['coupon' => ['This coupon does not apply to the selected plan.']]);
        }

        if ($coupon->max_redemptions !== null) {
            $totalRedemptions = CouponRedemption::withoutTenantScope()->where('coupon_id', $coupon->id)->count();

            if ($totalRedemptions >= $coupon->max_redemptions) {
                throw ValidationException::withMessages(['coupon' => ['This coupon has reached its redemption limit.']]);
            }
        }

        if ($coupon->per_tenant_limit !== null) {
            $tenantRedemptions = CouponRedemption::withoutTenantScope()
                ->where('coupon_id', $coupon->id)
                ->where('tenant_id', $tenant->id)
                ->count();

            if ($tenantRedemptions >= $coupon->per_tenant_limit) {
                throw ValidationException::withMessages([
                    'coupon' => ['You have already used this coupon the maximum number of times.'],
                ]);
            }
        }

        return ['coupon' => $coupon, 'discount' => $coupon->discountFor($subtotal)];
    }

    public function redeem(Coupon $coupon, Tenant $tenant, Invoice $invoice, int $discountAmount): CouponRedemption
    {
        return CouponRedemption::query()->create([
            'coupon_id' => $coupon->id,
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'amount_discounted' => $discountAmount,
        ]);
    }
}
