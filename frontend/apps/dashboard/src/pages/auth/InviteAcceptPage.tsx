import { Button, Input } from '@fitmirror/ui';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Navigate, useNavigate, useSearchParams } from 'react-router-dom';

import { acceptStaffInvitation, ApiError, mapFieldErrors } from '../../lib/auth';
import { useDashboardAuthStore } from '../../stores/dashboardAuthStore';

/** Lands from StaffInvitationNotification's emailed link — see its docblock. */
export function InviteAcceptPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? '';
  const isAuthenticated = useDashboardAuthStore((state) => state.isAuthenticated);

  const [name, setName] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (isAuthenticated) {
    return <Navigate to="/" replace />;
  }

  if (!token) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-neutral-100 px-4">
        <p className="text-danger-600 text-sm">This invitation link is missing its token.</p>
      </div>
    );
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setFormError(null);

    const errors: Record<string, string> = {};
    if (!name.trim()) errors.name = 'Your name is required.';
    if (password.length < 10) errors.password = 'Password must be at least 10 characters.';
    if (password !== passwordConfirmation) errors.password_confirmation = 'Passwords do not match.';
    setFieldErrors(errors);
    if (Object.keys(errors).length > 0) return;

    setIsSubmitting(true);
    try {
      await acceptStaffInvitation({
        token,
        name: name.trim(),
        password,
        password_confirmation: passwordConfirmation,
      });
      navigate('/', { replace: true });
    } catch (error) {
      setFormError(error instanceof ApiError ? error.message : 'Unable to accept this invitation.');
      setFieldErrors(mapFieldErrors(error));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-neutral-100 px-4">
      <div className="w-full max-w-sm rounded-lg border border-neutral-200 bg-white p-8 shadow-sm">
        <div className="mb-6 text-center">
          <p className="text-sm font-semibold uppercase tracking-wide text-neutral-400">FitMirror</p>
          <h1 className="mt-1 text-xl font-semibold text-neutral-900">Join your team</h1>
          <p className="mt-2 text-sm text-neutral-500">Set your name and password to activate your account.</p>
        </div>

        <form className="flex flex-col gap-4" onSubmit={(event) => void handleSubmit(event)} noValidate>
          <Input
            label={t('auth.name')}
            value={name}
            onChange={(event) => setName(event.target.value)}
            error={fieldErrors.name}
            disabled={isSubmitting}
            required
            autoFocus
          />
          <Input
            type="password"
            autoComplete="new-password"
            label={t('auth.password')}
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            error={fieldErrors.password}
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
            Activate account
          </Button>
        </form>
      </div>
    </div>
  );
}
