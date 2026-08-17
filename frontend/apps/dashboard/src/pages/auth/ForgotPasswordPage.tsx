import { Button, Input } from '@fitmirror/ui';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';

import { ApiError, forgotPassword } from '../../lib/auth';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function ForgotPasswordPage() {
  const { t } = useTranslation();
  const [email, setEmail] = useState('');
  const [fieldError, setFieldError] = useState<string | undefined>();
  const [message, setMessage] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setMessage(null);

    if (!email.trim() || !EMAIL_PATTERN.test(email.trim())) {
      setFieldError('Enter a valid email address.');
      return;
    }
    setFieldError(undefined);

    setIsSubmitting(true);
    try {
      const result = await forgotPassword(email.trim());
      setMessage(result);
    } catch (error) {
      setMessage(error instanceof ApiError ? error.message : 'Something went wrong. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-neutral-100 px-4">
      <div className="w-full max-w-sm rounded-lg border border-neutral-200 bg-white p-8 shadow-sm">
        <div className="mb-6 text-center">
          <p className="text-sm font-semibold uppercase tracking-wide text-neutral-400">FitMirror</p>
          <h1 className="mt-1 text-xl font-semibold text-neutral-900">Reset your password</h1>
          <p className="mt-2 text-sm text-neutral-500">
            We'll email you a link to reset your password if an account exists for that address.
          </p>
        </div>

        {message ? (
          <p className="rounded-md bg-neutral-50 p-4 text-center text-sm text-neutral-700">{message}</p>
        ) : (
          <form className="flex flex-col gap-4" onSubmit={(event) => void handleSubmit(event)} noValidate>
            <Input
              type="email"
              autoComplete="email"
              label={t('auth.email')}
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              error={fieldError}
              disabled={isSubmitting}
              required
              autoFocus
            />
            <Button type="submit" className="mt-2" isLoading={isSubmitting}>
              Send reset link
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
