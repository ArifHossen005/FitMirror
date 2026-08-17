import { useTranslation } from 'react-i18next';
import { Navigate } from 'react-router-dom';

import { logout } from '../../lib/auth';
import { useDashboardAuthStore } from '../../stores/dashboardAuthStore';

/**
 * Shown while `tenant.status` is `pending` (or `rejected`) — the account
 * exists and the owner can log in, but EnsureTenantIsActive blocks every
 * real business route until Mission Control approves the tenant. The
 * product document frames this as "shown after payment"; since billing
 * (Phase 3.A/3.C) doesn't exist yet, approval today is purely a Mission
 * Control action on the pending queue — this screen reflects that
 * honestly rather than referencing a payment step that isn't real yet.
 */
export function PendingApprovalPage() {
  const { t } = useTranslation();
  const tenant = useDashboardAuthStore((state) => state.tenant);

  if (!tenant || (tenant.status !== 'pending' && tenant.status !== 'rejected')) {
    return <Navigate to="/" replace />;
  }

  const isRejected = tenant.status === 'rejected';

  return (
    <div className="flex min-h-screen items-center justify-center bg-neutral-100 px-4">
      <div className="w-full max-w-md rounded-lg border border-neutral-200 bg-white p-8 text-center shadow-sm">
        <p className="text-sm font-semibold uppercase tracking-wide text-neutral-400">FitMirror</p>
        <h1 className="mt-1 text-xl font-semibold text-neutral-900">
          {isRejected ? 'Application not approved' : 'Awaiting approval'}
        </h1>
        <p className="mt-3 text-sm text-neutral-600">
          {isRejected
            ? `${tenant.name} was not approved. Contact support if you believe this is a mistake.`
            : `${tenant.name} is waiting for the FitMirror team to review and approve your account. You'll get an email the moment it's ready.`}
        </p>
        <p className="mt-4 text-sm text-neutral-500">
          Questions? Email{' '}
          <a href="mailto:support@fitmirror.com" className="text-brand-600 hover:underline">
            support@fitmirror.com
          </a>
        </p>
        <button
          type="button"
          onClick={() => void logout()}
          className="text-brand-600 mt-6 text-sm font-medium hover:underline"
        >
          {t('actions.logout')}
        </button>
      </div>
    </div>
  );
}
