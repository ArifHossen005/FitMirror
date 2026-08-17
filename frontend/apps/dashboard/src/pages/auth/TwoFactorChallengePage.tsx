import { Button, Input } from '@fitmirror/ui';
import { type FormEvent, useState } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';

import { ApiError, completeTwoFactorChallenge } from '../../lib/auth';
import { useDashboardAuthStore } from '../../stores/dashboardAuthStore';

interface LocationState {
  twoFactorToken?: string;
  from?: { from?: string } | string;
}

/** Second step of login for an account with 2FA enabled — see LoginPage. */
export function TwoFactorChallengePage() {
  const navigate = useNavigate();
  const location = useLocation();
  const isAuthenticated = useDashboardAuthStore((state) => state.isAuthenticated);
  const state = (location.state as LocationState | null) ?? {};

  const [code, setCode] = useState('');
  const [useRecoveryCode, setUseRecoveryCode] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (isAuthenticated) {
    return <Navigate to="/" replace />;
  }

  if (!state.twoFactorToken) {
    return <Navigate to="/login" replace />;
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);

    if (!code.trim()) {
      setError(useRecoveryCode ? 'Enter a recovery code.' : 'Enter the 6-digit code from your authenticator app.');
      return;
    }

    setIsSubmitting(true);
    try {
      await completeTwoFactorChallenge(
        state.twoFactorToken!,
        useRecoveryCode ? undefined : code.trim(),
        useRecoveryCode ? code.trim() : undefined,
      );

      const from = typeof state.from === 'object' ? (state.from?.from ?? '/') : '/';
      navigate(from, { replace: true });
    } catch (submitError) {
      setError(submitError instanceof ApiError ? submitError.message : 'Verification failed.');
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-neutral-100 px-4">
      <div className="w-full max-w-sm rounded-lg border border-neutral-200 bg-white p-8 shadow-sm">
        <div className="mb-6 text-center">
          <p className="text-sm font-semibold uppercase tracking-wide text-neutral-400">FitMirror</p>
          <h1 className="mt-1 text-xl font-semibold text-neutral-900">Two-factor verification</h1>
          <p className="mt-2 text-sm text-neutral-500">
            {useRecoveryCode
              ? 'Enter one of your saved recovery codes.'
              : 'Enter the 6-digit code from your authenticator app.'}
          </p>
        </div>

        <form className="flex flex-col gap-4" onSubmit={(event) => void handleSubmit(event)} noValidate>
          <Input
            autoComplete="one-time-code"
            label={useRecoveryCode ? 'Recovery code' : 'Authentication code'}
            value={code}
            onChange={(event) => setCode(event.target.value)}
            disabled={isSubmitting}
            autoFocus
            required
          />

          {error && (
            <p role="alert" className="text-danger-600 text-sm">
              {error}
            </p>
          )}

          <Button type="submit" className="mt-2" isLoading={isSubmitting}>
            Verify
          </Button>
        </form>

        <button
          type="button"
          onClick={() => {
            setUseRecoveryCode((value) => !value);
            setCode('');
            setError(null);
          }}
          className="text-brand-600 mt-4 w-full text-center text-sm hover:underline"
        >
          {useRecoveryCode ? 'Use an authenticator code instead' : "Can't access your authenticator? Use a recovery code"}
        </button>
      </div>
    </div>
  );
}
