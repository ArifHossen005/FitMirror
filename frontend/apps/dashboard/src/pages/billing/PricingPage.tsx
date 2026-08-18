import { Button, Skeleton } from '@fitmirror/ui';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';

import { type BillingCycle, fetchPublicPlans } from '../../lib/billing';
import { useDashboardAuthStore } from '../../stores/dashboardAuthStore';

const FEATURE_LABELS: Record<string, string> = {
  campaign_manager: 'Campaign Manager',
  loyalty_program: 'Loyalty Program',
  social_media_post: 'Social Media Post',
  analytics: 'Analytics',
  custom_branding: 'Custom Branding',
  api_access: 'API Access',
};

const LIMIT_LABELS: Record<string, string> = {
  try_on_sessions_per_day: 'Try-on sessions / day',
  categories: 'Categories',
  skus: 'Products (SKU)',
  staff_accounts: 'Staff accounts',
  storage_gb: 'Storage',
  branches: 'Branches',
};

function formatLimit(value: number | null, key: string): string {
  if (value === null) return 'Unlimited';
  return key === 'storage_gb' ? `${value} GB` : String(value);
}

/**
 * Public — reachable unauthenticated, same as RegisterPage/LoginPage.
 * Reads live from GET /plans (Phase 3.D) rather than hardcoding the
 * comparison table, so a Mission Control price/limit edit shows up here
 * without a frontend deploy — see PlanListController's own docblock.
 */
export function PricingPage() {
  const navigate = useNavigate();
  const isAuthenticated = useDashboardAuthStore((state) => state.isAuthenticated);
  const [cycle, setCycle] = useState<BillingCycle>('monthly');

  const plansQuery = useQuery({ queryKey: ['plans', 'public'], queryFn: fetchPublicPlans });

  const featureKeys = Object.keys(FEATURE_LABELS);
  const limitKeys = Object.keys(LIMIT_LABELS);

  function choosePlan(slug: string) {
    if (slug === 'free') {
      navigate(isAuthenticated ? '/' : '/register');
      return;
    }

    navigate(isAuthenticated ? `/billing/checkout?plan=${slug}&cycle=${cycle}` : '/register');
  }

  return (
    <div className="mx-auto max-w-5xl px-4 py-16">
      <div className="text-center">
        <p className="text-brand-600 text-sm font-semibold uppercase tracking-wide">FitMirror</p>
        <h1 className="mt-2 text-3xl font-bold text-neutral-900">Simple, transparent pricing</h1>
        <p className="mt-3 text-neutral-500">Choose the plan that fits your shop. Upgrade anytime.</p>
      </div>

      <div className="mt-8 flex justify-center">
        <div className="inline-flex rounded-lg border border-neutral-200 bg-white p-1">
          <button
            type="button"
            onClick={() => setCycle('monthly')}
            className={`rounded-md px-4 py-1.5 text-sm font-medium ${cycle === 'monthly' ? 'bg-brand-600 text-white' : 'text-neutral-600'}`}
          >
            Monthly
          </button>
          <button
            type="button"
            onClick={() => setCycle('yearly')}
            className={`rounded-md px-4 py-1.5 text-sm font-medium ${cycle === 'yearly' ? 'bg-brand-600 text-white' : 'text-neutral-600'}`}
          >
            Yearly <span className="text-success-600 ml-1">(20% off)</span>
          </button>
        </div>
      </div>

      {plansQuery.isLoading && (
        <div className="mt-10 grid grid-cols-1 gap-6 md:grid-cols-3">
          {[1, 2, 3].map((i) => (
            <Skeleton key={i} className="h-96 rounded-lg" />
          ))}
        </div>
      )}

      {plansQuery.data && (
        <div className="mt-10 grid grid-cols-1 gap-6 md:grid-cols-3">
          {plansQuery.data.map((plan) => {
            const price = cycle === 'yearly' ? plan.price_yearly : plan.price_monthly;
            const isPro = plan.slug === 'pro';

            return (
              <div
                key={plan.id}
                className={`rounded-xl border p-6 ${isPro ? 'border-brand-500 shadow-lg' : 'border-neutral-200'}`}
              >
                {isPro && (
                  <p className="text-brand-600 mb-2 text-xs font-semibold uppercase tracking-wide">
                    Most Popular
                  </p>
                )}
                <h2 className="text-lg font-semibold text-neutral-900">{plan.name}</h2>
                <p className="mt-2">
                  <span className="text-3xl font-bold text-neutral-900">৳{price}</span>
                  <span className="text-sm text-neutral-500">/{cycle === 'yearly' ? 'year' : 'month'}</span>
                </p>
                {plan.trial_days > 0 && (
                  <p className="text-success-600 mt-1 text-xs">{plan.trial_days}-day free trial</p>
                )}

                <Button className="mt-4 w-full" variant={isPro ? 'primary' : 'outline'} onClick={() => choosePlan(plan.slug)}>
                  {plan.slug === 'free' ? 'Get Started' : 'Choose Plan'}
                </Button>

                <ul className="mt-6 flex flex-col gap-2 text-sm">
                  {limitKeys.map((key) => (
                    <li key={key} className="flex justify-between text-neutral-600">
                      <span>{LIMIT_LABELS[key]}</span>
                      <span className="font-medium text-neutral-900">
                        {formatLimit(plan.limits[key] ?? null, key)}
                      </span>
                    </li>
                  ))}
                  {featureKeys.map((key) => {
                    const feature = plan.features[key];
                    return (
                      <li key={key} className="flex justify-between text-neutral-600">
                        <span>{FEATURE_LABELS[key]}</span>
                        <span className={feature?.enabled ? 'text-success-600 font-medium' : 'text-neutral-300'}>
                          {feature?.enabled ? (feature.tier ?? '✓') : '—'}
                        </span>
                      </li>
                    );
                  })}
                </ul>
              </div>
            );
          })}
        </div>
      )}

      <p className="mt-10 text-center text-sm text-neutral-500">
        Already have an account?{' '}
        <Link to="/login" className="text-brand-600 font-medium hover:underline">
          Log in
        </Link>
      </p>
    </div>
  );
}
