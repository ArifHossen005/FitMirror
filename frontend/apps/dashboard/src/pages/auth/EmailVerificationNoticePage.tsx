import { Button } from '@fitmirror/ui';
import { useState } from 'react';
import { Link, Navigate, useLocation } from 'react-router-dom';

import { ApiError, resendVerificationEmail } from '../../lib/auth';
import { useDashboardAuthStore } from '../../stores/dashboardAuthStore';

/**
 * Reached two ways: right after RegisterPage (via router state, no session
 * yet — the owner isn't logged in until they verify + log in), or as a
 * logged-in user whose email genuinely isn't verified yet (ProtectedLayout
 * redirects here in that case, Phase 2.D's other half of this page).
 */
export function EmailVerificationNoticePage() {
  const location = useLocation();
  const isAuthenticated = useDashboardAuthStore((state) => state.isAuthenticated);
  const user = useDashboardAuthStore((state) => state.user);
  const emailFromRegistration = (location.state as { email?: string } | null)?.email;
  const email = user?.email ?? emailFromRegistration ?? null;

  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (isAuthenticated && user?.emailVerified) {
    return <Navigate to="/" replace />;
  }

  async function handleResend() {
    setMessage(null);
    setError(null);
    setIsSubmitting(true);
    try {
      const text = await resendVerificationEmail();
      setMessage(text);
    } catch (submitError) {
      setError(submitError instanceof ApiError ? submitError.message : 'Unable to resend right now.');
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-neutral-100 px-4">
      <div className="w-full max-w-md rounded-lg border border-neutral-200 bg-white p-8 text-center shadow-sm">
        <p className="text-sm font-semibold uppercase tracking-wide text-neutral-400">FitMirror</p>
        <h1 className="mt-1 text-xl font-semibold text-neutral-900">Check your email</h1>
        <p className="mt-3 text-sm text-neutral-600">
          We sent a verification link to{' '}
          {email ? <span className="font-medium text-neutral-900">{email}</span> : 'your email address'}. Click
          it to activate your account.
        </p>

        {message && <p className="mt-4 rounded-md bg-neutral-50 p-3 text-sm text-neutral-700">{message}</p>}
        {error && (
          <p role="alert" className="text-danger-600 mt-4 text-sm">
            {error}
          </p>
        )}

        {isAuthenticated && (
          <Button className="mt-6 w-full" variant="outline" isLoading={isSubmitting} onClick={() => void handleResend()}>
            Resend verification email
          </Button>
        )}

        <p className="mt-6 text-sm text-neutral-500">
          <Link to="/login" className="text-brand-600 font-medium hover:underline">
            Back to sign in
          </Link>
        </p>
      </div>
    </div>
  );
}
