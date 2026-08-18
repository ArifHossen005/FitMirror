import { Button, Input } from '@fitmirror/ui';
import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';

import {
  ApiError,
  type BillingCycle,
  type CouponPreview,
  fetchPublicPlans,
  initiatePayment,
  previewCoupon,
} from '../../lib/billing';

/**
 * `?plan={slug}&cycle=monthly|yearly` — reads the query string rather than
 * a route param so PricingPage can link here with a plain URL. On submit,
 * POST /payment/initiate returns SSLCommerz's own hosted checkout URL;
 * this page just hands the browser off to it (`window.location.href`) —
 * there is no in-app card form, SSLCommerz's page is where payment
 * actually happens.
 */
export function CheckoutPage() {
  const [params] = useSearchParams();
  const planSlug = params.get('plan') ?? 'pro';
  const cycle = (params.get('cycle') as BillingCycle | null) ?? 'monthly';

  const [couponCode, setCouponCode] = useState('');
  const [appliedCoupon, setAppliedCoupon] = useState<CouponPreview | null>(null);
  const [couponError, setCouponError] = useState<string | null>(null);
  const [submitError, setSubmitError] = useState<string | null>(null);

  const plansQuery = useQuery({ queryKey: ['plans', 'public'], queryFn: fetchPublicPlans });
  const plan = plansQuery.data?.find((p) => p.slug === planSlug);

  const couponMutation = useMutation({
    mutationFn: () => previewCoupon(couponCode.trim(), plan?.id ?? 0, cycle),
    onSuccess: (result) => {
      setAppliedCoupon(result);
      setCouponError(null);
    },
    onError: (error) => {
      setAppliedCoupon(null);
      setCouponError(error instanceof ApiError ? error.message : 'Unable to validate this coupon.');
    },
  });

  const payMutation = useMutation({
    mutationFn: () => initiatePayment(plan?.id ?? 0, cycle, appliedCoupon ? couponCode.trim() : undefined),
    onSuccess: (result) => {
      window.location.href = result.gateway_url;
    },
    onError: (error) => {
      setSubmitError(error instanceof ApiError ? error.message : 'Unable to start checkout. Please try again.');
    },
  });

  if (plansQuery.isLoading) {
    return <div className="p-6 text-neutral-500">Loading…</div>;
  }

  if (!plan) {
    return <div className="p-6 text-neutral-500">That plan could not be found.</div>;
  }

  const subtotal = cycle === 'yearly' ? plan.price_yearly : plan.price_monthly;
  const discount = appliedCoupon?.discount ?? 0;
  const estimatedTotal = subtotal - discount;

  return (
    <div className="mx-auto flex max-w-lg flex-col gap-6 p-6">
      <div>
        <h1 className="text-lg font-semibold text-neutral-900">Checkout</h1>
        <p className="mt-1 text-sm text-neutral-500">You're subscribing to the {plan.name} plan.</p>
      </div>

      <div className="rounded-lg border border-neutral-200 bg-white p-5">
        <div className="flex items-center justify-between">
          <span className="text-sm text-neutral-600">{plan.name} plan — {cycle}</span>
          <span className="text-sm font-medium text-neutral-900">৳{subtotal}</span>
        </div>

        {appliedCoupon && (
          <div className="text-success-600 mt-2 flex items-center justify-between text-sm">
            <span>Coupon "{appliedCoupon.code}"</span>
            <span>-৳{discount}</span>
          </div>
        )}

        <div className="mt-4 flex gap-2">
          <Input
            placeholder="Coupon code"
            value={couponCode}
            onChange={(event) => setCouponCode(event.target.value.toUpperCase())}
            className="flex-1"
          />
          <Button
            type="button"
            variant="outline"
            isLoading={couponMutation.isPending}
            disabled={!couponCode.trim()}
            onClick={() => couponMutation.mutate()}
          >
            Apply
          </Button>
        </div>
        {couponError && <p className="text-danger-600 mt-1 text-xs">{couponError}</p>}
        {appliedCoupon && (
          <button
            type="button"
            className="mt-1 text-xs text-neutral-500 hover:underline"
            onClick={() => {
              setAppliedCoupon(null);
              setCouponCode('');
              setCouponError(null);
            }}
          >
            Remove coupon
          </button>
        )}

        <div className="mt-4 flex items-center justify-between border-t border-neutral-200 pt-4">
          <span className="text-sm font-semibold text-neutral-900">Estimated total (before VAT)</span>
          <span className="text-lg font-bold text-neutral-900">৳{estimatedTotal}</span>
        </div>
        <p className="mt-1 text-xs text-neutral-400">
          VAT is calculated at checkout and shown on the SSLCommerz payment page.
        </p>
      </div>

      {submitError && (
        <p role="alert" className="text-danger-600 text-sm">
          {submitError}
        </p>
      )}

      <Button size="lg" isLoading={payMutation.isPending} onClick={() => payMutation.mutate()}>
        Continue to Payment
      </Button>
    </div>
  );
}
