import { Button, Input, Select } from '@fitmirror/ui';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';

import { ApiError, changePassword, mapFieldErrors, updateProfile } from '../../lib/auth';
import { useDashboardAuthStore } from '../../stores/dashboardAuthStore';

export function ProfileSettingsPage() {
  const user = useDashboardAuthStore((state) => state.user);

  return (
    <div className="flex max-w-xl flex-col gap-10">
      <ProfileDetailsForm user={user} />
      <PasswordChangeForm />
      <div>
        <h2 className="text-base font-semibold text-neutral-900">Security</h2>
        <div className="mt-2 flex flex-col gap-2 text-sm">
          <Link to="/settings/two-factor" className="text-brand-600 hover:underline">
            Two-factor authentication settings
          </Link>
          <Link to="/settings/sessions" className="text-brand-600 hover:underline">
            Active sessions
          </Link>
        </div>
      </div>
    </div>
  );
}

function ProfileDetailsForm({ user }: { user: ReturnType<typeof useDashboardAuthStore.getState>['user'] }) {
  const { t } = useTranslation();
  const [name, setName] = useState(user?.name ?? '');
  const [phone, setPhone] = useState(user?.phone ?? '');
  const [locale, setLocale] = useState<'bn' | 'en'>(user?.locale ?? 'bn');
  const [avatarFile, setAvatarFile] = useState<File | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<{ text: string; isError: boolean } | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setMessage(null);
    setFieldErrors({});

    setIsSubmitting(true);
    try {
      await updateProfile({
        name: name.trim(),
        phone: phone.trim() || null,
        locale,
        avatar: avatarFile ?? undefined,
      });
      setMessage({ text: 'Profile updated.', isError: false });
      setAvatarFile(null);
    } catch (error) {
      setFieldErrors(mapFieldErrors(error));
      setMessage({
        text: error instanceof ApiError ? error.message : 'Unable to update profile.',
        isError: true,
      });
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div>
      <h2 className="text-base font-semibold text-neutral-900">Profile</h2>
      <form className="mt-3 flex flex-col gap-4" onSubmit={(event) => void handleSubmit(event)} noValidate>
        <div className="flex items-center gap-4">
          {user?.avatar ? (
            <img src={user.avatar} alt={user.name} className="h-16 w-16 rounded-full object-cover" />
          ) : (
            <div className="bg-brand-100 text-brand-700 flex h-16 w-16 items-center justify-center rounded-full text-xl font-semibold">
              {(user?.name ?? '?').charAt(0).toUpperCase()}
            </div>
          )}
          <label className="text-sm">
            <span className="text-brand-600 cursor-pointer hover:underline">Change avatar</span>
            <input
              type="file"
              accept="image/*"
              className="sr-only"
              onChange={(event) => setAvatarFile(event.target.files?.[0] ?? null)}
            />
            {avatarFile && <span className="ml-2 text-neutral-500">{avatarFile.name}</span>}
          </label>
        </div>

        <Input
          label={t('auth.name')}
          value={name}
          onChange={(event) => setName(event.target.value)}
          error={fieldErrors.name}
          disabled={isSubmitting}
          required
        />
        <Input
          type="tel"
          label={t('auth.phone')}
          value={phone}
          onChange={(event) => setPhone(event.target.value)}
          error={fieldErrors.phone}
          disabled={isSubmitting}
        />
        <Select
          label="Language"
          value={locale}
          onChange={(event) => setLocale(event.target.value as 'bn' | 'en')}
          options={[
            { value: 'bn', label: 'বাংলা' },
            { value: 'en', label: 'English' },
          ]}
          disabled={isSubmitting}
        />

        {message && (
          <p role={message.isError ? 'alert' : undefined} className={message.isError ? 'text-danger-600 text-sm' : 'text-success-600 text-sm'}>
            {message.text}
          </p>
        )}

        <Button type="submit" className="w-fit" isLoading={isSubmitting}>
          {t('actions.save')}
        </Button>
      </form>
    </div>
  );
}

function PasswordChangeForm() {
  const { t } = useTranslation();
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [newPasswordConfirmation, setNewPasswordConfirmation] = useState('');
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<{ text: string; isError: boolean } | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setMessage(null);

    const errors: Record<string, string> = {};
    if (!currentPassword) errors.current_password = 'Enter your current password.';
    if (newPassword.length < 10) errors.new_password = 'Password must be at least 10 characters.';
    if (newPassword !== newPasswordConfirmation) errors.new_password_confirmation = 'Passwords do not match.';
    setFieldErrors(errors);
    if (Object.keys(errors).length > 0) return;

    setIsSubmitting(true);
    try {
      await changePassword({
        current_password: currentPassword,
        new_password: newPassword,
        new_password_confirmation: newPasswordConfirmation,
      });
      setMessage({ text: 'Password changed. Other sessions were signed out.', isError: false });
      setCurrentPassword('');
      setNewPassword('');
      setNewPasswordConfirmation('');
    } catch (error) {
      setFieldErrors(mapFieldErrors(error));
      setMessage({
        text: error instanceof ApiError ? error.message : 'Unable to change password.',
        isError: true,
      });
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div>
      <h2 className="text-base font-semibold text-neutral-900">Change password</h2>
      <form className="mt-3 flex flex-col gap-4" onSubmit={(event) => void handleSubmit(event)} noValidate>
        <Input
          type="password"
          autoComplete="current-password"
          label="Current password"
          value={currentPassword}
          onChange={(event) => setCurrentPassword(event.target.value)}
          error={fieldErrors.current_password}
          disabled={isSubmitting}
        />
        <Input
          type="password"
          autoComplete="new-password"
          label="New password"
          value={newPassword}
          onChange={(event) => setNewPassword(event.target.value)}
          error={fieldErrors.new_password}
          disabled={isSubmitting}
        />
        <Input
          type="password"
          autoComplete="new-password"
          label={t('auth.confirmPassword')}
          value={newPasswordConfirmation}
          onChange={(event) => setNewPasswordConfirmation(event.target.value)}
          error={fieldErrors.new_password_confirmation}
          disabled={isSubmitting}
        />

        {message && (
          <p role={message.isError ? 'alert' : undefined} className={message.isError ? 'text-danger-600 text-sm' : 'text-success-600 text-sm'}>
            {message.text}
          </p>
        )}

        <Button type="submit" className="w-fit" isLoading={isSubmitting}>
          {t('actions.save')}
        </Button>
      </form>
    </div>
  );
}
