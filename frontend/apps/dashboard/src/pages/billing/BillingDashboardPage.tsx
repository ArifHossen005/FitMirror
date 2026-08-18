import { Button } from '@fitmirror/ui';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link } from 'react-router-dom';

import {
  ApiError,
  cancelSubscription,
  fetchCurrentSubscription,
  fetchPlanUsage,
  setAutoRenew,
} from '../../lib/billing';
import { useDashboardAuthStore } from '../../stores/dashboardAuthStore';

const STATUS_LABELS: Record<string, string> = {
  pending_payment: 'Pending Payment',
  pending_approval: 'Pending Approval',
  trialing: 'Trial',
  active: 'Active',
  past_due: 'Past Due',
  grace: 'Grace Period',
  suspended: 'Suspended',
  cancelled: 'Cancelled',
  expired: 'Expired',
};

function formatLimitLabel(key: string): string {
  return key
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

/**
 * Current plan, usage meters, and subscription controls — the tenant
 * owner's billing home. `user.isTenantOwner` gates the auto-renew/cancel
 * actions client-side (mirroring the backend's own owner-only checks on
 * those endpoints); usage and plan info are visible to anyone with
 * `billing.view`.
 */
export function BillingDashboardPage() {
  const plan = useDashboardAuthStore((state) => state.plan);
  const isOwner = useDashboardAuthStore((state) => state.user?.isTenantOwner ?? false);
  const queryClient = useQueryClient();
  const [cancelError, setCancelError] = useState<string | null>(null);

  const usageQuery = useQuery({ queryKey: ['plan', 'usage'], queryFn: fetchPlanUsage });
  const subscriptionQuery = useQuery({ queryKey: ['subscription'], queryFn: fetchCurrentSubscription });

  const autoRenewMutation = useMutation({
    mutationFn: (value: boolean) => setAutoRenew(value),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['subscription'] }),
  });

  const cancelMutation = useMutation({
    mutationFn: () => cancelSubscription(false, 'Cancelled from the billing dashboard'),
    onSuccess: () => {
      setCancelError(null);
      void queryClient.invalidateQueries({ queryKey: ['subscription'] });
    },
    onError: (error) => {
      setCancelError(error instanceof ApiError ? error.message : 'Unable to cancel the subscription.');
    },
  });

  const subscription = subscriptionQuery.data;

  return (
    <div className="flex flex-col gap-8 p-6">
      <div>
        <h1 className="text-lg font-semibold text-neutral-900">Billing</h1>
        <p className="mt-1 text-sm text-neutral-500">Your current plan, usage, and subscription.</p>
      </div>

      <div className="rounded-lg border border-neutral-200 bg-white p-5">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-xs font-medium uppercase tracking-wide text-neutral-400">Current Plan</p>
            <p className="mt-1 text-xl font-semibold text-neutral-900">{plan?.name ?? 'Free'}</p>
          </div>
          {subscription && (
            <span className="rounded-full bg-neutral-100 px-3 py-1 text-xs font-medium text-neutral-700">
              {STATUS_LABELS[subscription.status] ?? subscription.status}
            </span>
          )}
        </div>

        {!subscription && !subscriptionQuery.isLoading && (
          <div className="mt-4">
            <p className="text-sm text-neutral-500">You don't have an active subscription yet.</p>
            <Link to="/pricing" className="text-brand-600 mt-2 inline-block text-sm font-medium hover:underline">
              View plans →
            </Link>
          </div>
        )}

        {subscription && (
          <div className="mt-4 flex flex-col gap-3 border-t border-neutral-100 pt-4 text-sm">
            {subscription.trial_ends_at && (
              <p className="text-neutral-600">
                Trial ends {new Date(subscription.trial_ends_at).toLocaleDateString()}
              </p>
            )}
            {isOwner && (
              <div className="flex items-center justify-between">
                <span className="text-neutral-700">Auto-renew</span>
                <button
                  type="button"
                  role="switch"
                  aria-checked={subscription.auto_renew}
                  disabled={autoRenewMutation.isPending}
                  onClick={() => autoRenewMutation.mutate(!subscription.auto_renew)}
                  className={`h-6 w-11 rounded-full transition-colors ${subscription.auto_renew ? 'bg-brand-500' : 'bg-neutral-300'}`}
                >
                  <span
                    className={`block h-5 w-5 translate-x-0.5 rounded-full bg-white transition-transform ${subscription.auto_renew ? 'translate-x-5' : ''}`}
                  />
                </button>
              </div>
            )}
            {isOwner && subscription.status !== 'cancelled' && (
              <div>
                <Button
                  variant="outline"
                  size="sm"
                  isLoading={cancelMutation.isPending}
                  onClick={() => {
                    if (window.confirm('Cancel your subscription at the end of the current period?')) {
                      cancelMutation.mutate();
                    }
                  }}
                >
                  Cancel subscription
                </Button>
                {cancelError && <p className="text-danger-600 mt-2 text-xs">{cancelError}</p>}
              </div>
            )}
          </div>
        )}
      </div>

      <div>
        <h2 className="text-base font-semibold text-neutral-900">Usage</h2>
        <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
          {usageQuery.data?.usage.map((row) => (
            <div key={row.key} className="rounded-lg border border-neutral-200 bg-white p-4">
              <p className="text-sm text-neutral-500">{formatLimitLabel(row.key)}</p>
              <p className="mt-1 text-lg font-semibold text-neutral-900">
                {row.current ?? '—'} / {row.unlimited ? '∞' : (row.limit ?? '—')}
              </p>
              {!row.unlimited && row.limit && row.current !== null && (
                <div className="mt-2 h-1.5 w-full rounded-full bg-neutral-100">
                  <div
                    className="bg-brand-500 h-1.5 rounded-full"
                    style={{ width: `${Math.min(100, (row.current / row.limit) * 100)}%` }}
                  />
                </div>
              )}
            </div>
          ))}
        </div>
      </div>

      <div className="flex gap-4">
        <Link to="/billing/invoices" className="text-brand-600 text-sm font-medium hover:underline">
          View invoices →
        </Link>
        <Link to="/billing/addons" className="text-brand-600 text-sm font-medium hover:underline">
          Add-on marketplace →
        </Link>
      </div>
    </div>
  );
}
