import { tokenStorage } from '@fitmirror/api';
import { Button, Checkbox, Input } from '@fitmirror/ui';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';

import { ApiError, login } from '../../lib/auth';
import { useDashboardAuthStore } from '../../stores/dashboardAuthStore';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const REMEMBER_ME_KEY = 'fitmirror.dashboard.remember-me';

interface FieldErrors {
  email?: string;
  password?: string;
}

/**
 * "Remember me" has no server-side meaning here — Sanctum issues a single
 * plain bearer token with no session-vs-persistent distinction (unlike
 * cookie-based auth). Unchecking it means the token is cleared from
 * localStorage when the browser tab closes instead of surviving reloads
 * indefinitely; the checkbox's own state is remembered across visits so a
 * returning user isn't asked again every time.
 */
export function LoginPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();
  const isAuthenticated = useDashboardAuthStore((state) => state.isAuthenticated);

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [rememberMe, setRememberMe] = useState(() => localStorage.getItem(REMEMBER_ME_KEY) !== 'false');
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (isAuthenticated) {
    const redirectTo = (location.state as { from?: string } | null)?.from ?? '/';
    return <Navigate to={redirectTo} replace />;
  }

  function validate(): boolean {
    const errors: FieldErrors = {};
    if (!email.trim()) errors.email = 'Email is required.';
    else if (!EMAIL_PATTERN.test(email.trim())) errors.email = 'Enter a valid email address.';
    if (!password) errors.password = 'Password is required.';

    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setFormError(null);
    if (!validate()) return;

    setIsSubmitting(true);
    try {
      localStorage.setItem(REMEMBER_ME_KEY, String(rememberMe));

      const result = await login(email.trim(), password);

      if (result.status === 'two_factor_required') {
        navigate('/2fa-challenge', { state: { twoFactorToken: result.two_factor_token, from: location.state } });
        return;
      }

      if (!rememberMe) {
        // Sanctum tokens have no session-vs-persistent distinction of
        // their own (a single plain bearer token), so "don't remember me"
        // is implemented client-side: the token and session profile stay
        // fully usable for this browser tab's lifetime, but are wiped from
        // localStorage the moment the tab closes instead of surviving a
        // later visit. beforeunload fires reliably for a plain, synchronous
        // localStorage.removeItem — no async work is awaited here.
        window.addEventListener(
          'beforeunload',
          () => {
            tokenStorage.clear();
            useDashboardAuthStore.persist.clearStorage();
          },
          { once: true },
        );
      }

      const redirectTo = (location.state as { from?: string } | null)?.from ?? '/';
      navigate(redirectTo, { replace: true });
    } catch (error) {
      setFormError(error instanceof ApiError ? error.message : 'Login failed. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-neutral-100 px-4">
      <div className="w-full max-w-sm rounded-lg border border-neutral-200 bg-white p-8 shadow-sm">
        <div className="mb-6 text-center">
          <p className="text-sm font-semibold uppercase tracking-wide text-neutral-400">FitMirror</p>
          <h1 className="mt-1 text-xl font-semibold text-neutral-900">Sign in</h1>
        </div>

        <form className="flex flex-col gap-4" onSubmit={(event) => void handleSubmit(event)} noValidate>
          <Input
            type="email"
            autoComplete="email"
            label={t('auth.email')}
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            error={fieldErrors.email}
            disabled={isSubmitting}
            required
          />
          <Input
            type="password"
            autoComplete="current-password"
            label={t('auth.password')}
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            error={fieldErrors.password}
            disabled={isSubmitting}
            required
          />

          <div className="flex items-center justify-between">
            <Checkbox
              label={t('auth.rememberMe')}
              checked={rememberMe}
              onChange={(event) => setRememberMe(event.target.checked)}
              disabled={isSubmitting}
            />
            <Link to="/forgot-password" className="text-brand-600 text-sm hover:underline">
              {t('auth.forgotPassword')}
            </Link>
          </div>

          {formError && (
            <p role="alert" className="text-danger-600 text-sm">
              {formError}
            </p>
          )}

          <Button type="submit" className="mt-2" isLoading={isSubmitting}>
            {t('actions.login')}
          </Button>
        </form>

        <p className="mt-6 text-center text-sm text-neutral-500">
          New to FitMirror?{' '}
          <Link to="/register" className="text-brand-600 font-medium hover:underline">
            Create an account
          </Link>
        </p>
      </div>
    </div>
  );
}
