import { Button, Input } from '@fitmirror/ui';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';

import { missionLogin } from '../lib/missionAuth';
import { useMissionAuthStore } from '../stores/missionAuthStore';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

interface FieldErrors {
  email?: string;
  password?: string;
}

/**
 * Mission Control's own login page — never shares a form, store, or
 * endpoint with the tenant apps' auth (Phase 2.D, not yet built). Redirects
 * straight to "/" if a session already exists, so a logged-in super admin
 * hitting /login directly (e.g. a bookmark) doesn't see the form again.
 */
export function MissionLogin() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();
  const isAuthenticated = useMissionAuthStore((state) => state.isAuthenticated);

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (isAuthenticated) {
    const redirectTo = (location.state as { from?: string } | null)?.from ?? '/';
    return <Navigate to={redirectTo} replace />;
  }

  const validate = (): boolean => {
    const errors: FieldErrors = {};

    if (!email.trim()) {
      errors.email = 'Email is required.';
    } else if (!EMAIL_PATTERN.test(email.trim())) {
      errors.email = 'Enter a valid email address.';
    }

    if (!password) {
      errors.password = 'Password is required.';
    }

    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFormError(null);

    if (!validate()) return;

    setIsSubmitting(true);
    try {
      await missionLogin(email.trim(), password);
      navigate('/', { replace: true });
    } catch (error) {
      setFormError(error instanceof Error ? error.message : 'Login failed.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-neutral-100 px-4">
      <div className="w-full max-w-sm rounded-lg border border-neutral-200 bg-white p-8 shadow-sm">
        <div className="mb-6 text-center">
          <p className="text-sm font-semibold uppercase tracking-wide text-neutral-400">
            FitMirror
          </p>
          <h1 className="mt-1 text-xl font-semibold text-neutral-900">Mission Control</h1>
        </div>

        <form
          className="flex flex-col gap-4"
          onSubmit={(event) => void handleSubmit(event)}
          noValidate
        >
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

          {formError && (
            <p role="alert" className="text-danger-600 text-sm">
              {formError}
            </p>
          )}

          <Button type="submit" className="mt-2" isLoading={isSubmitting}>
            {t('actions.login')}
          </Button>
        </form>
      </div>
    </div>
  );
}
