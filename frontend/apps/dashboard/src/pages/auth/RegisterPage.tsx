import { Button, Input } from '@fitmirror/ui';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, Navigate, useNavigate } from 'react-router-dom';

import { ApiError, mapFieldErrors, register } from '../../lib/auth';
import { useDashboardAuthStore } from '../../stores/dashboardAuthStore';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

interface FieldErrors {
  tenant_name?: string;
  name?: string;
  email?: string;
  password?: string;
  password_confirmation?: string;
}

/**
 * Registration deliberately has no plan preselection UI — `plans` doesn't
 * exist until Phase 3.A. Every new tenant starts on the same `pending`
 * status regardless of which plan they'll eventually choose; plan
 * selection moves to the billing/checkout flow once it exists.
 */
export function RegisterPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const isAuthenticated = useDashboardAuthStore((state) => state.isAuthenticated);

  const [tenantName, setTenantName] = useState('');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (isAuthenticated) {
    return <Navigate to="/" replace />;
  }

  function validate(): boolean {
    const errors: FieldErrors = {};

    if (!tenantName.trim()) errors.tenant_name = 'Shop name is required.';
    if (!name.trim()) errors.name = 'Your name is required.';
    if (!email.trim()) {
      errors.email = 'Email is required.';
    } else if (!EMAIL_PATTERN.test(email.trim())) {
      errors.email = 'Enter a valid email address.';
    }
    if (password.length < 10) errors.password = 'Password must be at least 10 characters.';
    if (password !== passwordConfirmation) errors.password_confirmation = 'Passwords do not match.';

    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setFormError(null);

    if (!validate()) return;

    setIsSubmitting(true);
    try {
      await register({
        tenant_name: tenantName.trim(),
        name: name.trim(),
        email: email.trim(),
        phone: phone.trim() || undefined,
        password,
        password_confirmation: passwordConfirmation,
      });
      navigate('/verify-email', { replace: true, state: { email: email.trim() } });
    } catch (error) {
      const mapped = mapFieldErrors(error);
      setFieldErrors(mapped);
      setFormError(
        Object.keys(mapped).length === 0
          ? error instanceof ApiError
            ? error.message
            : 'Registration failed. Please try again.'
          : null,
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-neutral-100 px-4 py-10">
      <div className="w-full max-w-md rounded-lg border border-neutral-200 bg-white p-8 shadow-sm">
        <div className="mb-6 text-center">
          <p className="text-sm font-semibold uppercase tracking-wide text-neutral-400">FitMirror</p>
          <h1 className="mt-1 text-xl font-semibold text-neutral-900">Create your shop account</h1>
        </div>

        <form className="flex flex-col gap-4" onSubmit={(event) => void handleSubmit(event)} noValidate>
          <Input
            label={t('auth.tenantName')}
            value={tenantName}
            onChange={(event) => setTenantName(event.target.value)}
            error={fieldErrors.tenant_name}
            disabled={isSubmitting}
            required
          />
          <Input
            label={t('auth.name')}
            value={name}
            onChange={(event) => setName(event.target.value)}
            error={fieldErrors.name}
            disabled={isSubmitting}
            required
          />
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
            type="tel"
            label={`${t('auth.phone')} (optional)`}
            value={phone}
            onChange={(event) => setPhone(event.target.value)}
            disabled={isSubmitting}
          />
          <Input
            type="password"
            autoComplete="new-password"
            label={t('auth.password')}
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            error={fieldErrors.password}
            hint="At least 10 characters, mixed case, a number and a symbol."
            disabled={isSubmitting}
            required
          />
          <Input
            type="password"
            autoComplete="new-password"
            label={t('auth.confirmPassword')}
            value={passwordConfirmation}
            onChange={(event) => setPasswordConfirmation(event.target.value)}
            error={fieldErrors.password_confirmation}
            disabled={isSubmitting}
            required
          />

          {formError && (
            <p role="alert" className="text-danger-600 text-sm">
              {formError}
            </p>
          )}

          <Button type="submit" className="mt-2" isLoading={isSubmitting}>
            Create account
          </Button>
        </form>

        <p className="mt-6 text-center text-sm text-neutral-500">
          Already have an account?{' '}
          <Link to="/login" className="text-brand-600 font-medium hover:underline">
            {t('actions.login')}
          </Link>
        </p>
      </div>
    </div>
  );
}
