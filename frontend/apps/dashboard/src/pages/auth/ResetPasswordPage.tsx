import { Button, Input } from '@fitmirror/ui';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useSearchParams } from 'react-router-dom';

import { ApiError, mapFieldErrors, resetPassword } from '../../lib/auth';

/** Lands from the emailed reset link — see AppServiceProvider::configurePasswordResetUrl(). */
export function ResetPasswordPage() {
  const { t } = useTranslation();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? '';
  const email = searchParams.get('email') ?? '';

  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<{ text: string; isError: boolean } | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const linkIsValid = Boolean(token && email);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setMessage(null);

    const errors: Record<string, string> = {};
    if (password.length < 10) errors.password = 'Password must be at least 10 characters.';
    if (password !== passwordConfirmation) errors.password_confirmation = 'Passwords do not match.';
    setFieldErrors(errors);
    if (Object.keys(errors).length > 0) return;

    setIsSubmitting(true);
    try {
      const text = await resetPassword({
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      setMessage({ text, isError: false });
    } catch (error) {
      setMessage({
        text: error instanceof ApiError ? error.message : 'Something went wrong. Please try again.',
        isError: true,
      });
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
          <h1 className="mt-1 text-xl font-semibold text-neutral-900">Set a new password</h1>
        </div>

        {!linkIsValid ? (
          <p className="text-danger-600 text-center text-sm">
            This reset link is missing required information. Please request a new one.
          </p>
        ) : message && !message.isError ? (
          <p className="rounded-md bg-neutral-50 p-4 text-center text-sm text-neutral-700">{message.text}</p>
        ) : (
          <form className="flex flex-col gap-4" onSubmit={(event) => void handleSubmit(event)} noValidate>
            <Input
              type="password"
              autoComplete="new-password"
              label={t('auth.password')}
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              error={fieldErrors.password}
              disabled={isSubmitting}
              required
              autoFocus
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

            {message?.isError && (
              <p role="alert" className="text-danger-600 text-sm">
                {message.text}
              </p>
            )}

            <Button type="submit" className="mt-2" isLoading={isSubmitting}>
              Reset password
            </Button>
          </form>
        )}

        <p className="mt-6 text-center text-sm text-neutral-500">
          <Link to="/login" className="text-brand-600 font-medium hover:underline">
            Back to sign in
          </Link>
        </p>
      </div>
    </div>
  );
}
