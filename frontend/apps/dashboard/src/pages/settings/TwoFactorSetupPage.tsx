import { Button, Input } from '@fitmirror/ui';
import { type FormEvent, useState } from 'react';

import {
  ApiError,
  confirmTwoFactorSetup,
  disableTwoFactor,
  regenerateRecoveryCodes,
  startTwoFactorSetup,
  type TwoFactorSetup,
} from '../../lib/auth';
import { useDashboardAuthStore } from '../../stores/dashboardAuthStore';

function downloadRecoveryCodes(codes: string[]) {
  const blob = new Blob([codes.join('\n') + '\n'], { type: 'text/plain' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'fitmirror-recovery-codes.txt';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

function RecoveryCodesPanel({ codes, note }: { codes: string[]; note: string }) {
  return (
    <div className="mt-4 rounded-md border border-neutral-200 bg-neutral-50 p-4">
      <p className="text-sm font-medium text-neutral-800">{note}</p>
      <ul className="mt-3 grid grid-cols-2 gap-2 font-mono text-sm text-neutral-700">
        {codes.map((code) => (
          <li key={code}>{code}</li>
        ))}
      </ul>
      <Button variant="outline" size="sm" className="mt-4" onClick={() => downloadRecoveryCodes(codes)}>
        Download codes
      </Button>
    </div>
  );
}

/** Setup wizard (QR → confirm → recovery codes) plus disable/regenerate for an already-enabled account. */
export function TwoFactorSetupPage() {
  const user = useDashboardAuthStore((state) => state.user);
  const isEnabled = user?.twoFactorEnabled ?? false;

  const [setup, setSetup] = useState<TwoFactorSetup | null>(null);
  const [confirmCode, setConfirmCode] = useState('');
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleStart() {
    setError(null);
    setIsSubmitting(true);
    try {
      setSetup(await startTwoFactorSetup());
    } catch (submitError) {
      setError(submitError instanceof ApiError ? submitError.message : 'Unable to start setup.');
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleConfirm(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);

    if (!confirmCode.trim()) {
      setError('Enter the 6-digit code from your authenticator app.');
      return;
    }

    setIsSubmitting(true);
    try {
      const codes = await confirmTwoFactorSetup(confirmCode.trim());
      setRecoveryCodes(codes);
      setSetup(null);
    } catch (submitError) {
      setError(submitError instanceof ApiError ? submitError.message : 'That code is invalid.');
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDisable() {
    if (!window.confirm('Disable two-factor authentication? This makes your account easier to compromise.')) return;

    setError(null);
    setIsSubmitting(true);
    try {
      await disableTwoFactor();
      setRecoveryCodes(null);
      setSetup(null);
    } catch (submitError) {
      setError(submitError instanceof ApiError ? submitError.message : 'Unable to disable 2FA.');
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleRegenerate() {
    setError(null);
    setIsSubmitting(true);
    try {
      setRecoveryCodes(await regenerateRecoveryCodes());
    } catch (submitError) {
      setError(submitError instanceof ApiError ? submitError.message : 'Unable to regenerate codes.');
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="max-w-xl">
      <h1 className="text-lg font-semibold text-neutral-900">Two-factor authentication</h1>
      <p className="mt-1 text-sm text-neutral-500">
        {isEnabled
          ? 'Two-factor authentication is currently enabled on your account.'
          : 'Add an extra layer of security by requiring a code from an authenticator app at login.'}
        {user?.isTenantOwner && !isEnabled && (
          <span className="text-danger-600 mt-1 block font-medium">
            Required for shop owners — you won't be able to use most of the dashboard until this is set up.
          </span>
        )}
      </p>

      {error && (
        <p role="alert" className="text-danger-600 mt-3 text-sm">
          {error}
        </p>
      )}

      {recoveryCodes ? (
        <RecoveryCodesPanel codes={recoveryCodes} note="Save these recovery codes somewhere safe — each works once and they will not be shown again." />
      ) : isEnabled ? (
        <div className="mt-4 flex flex-wrap gap-3">
          <Button variant="outline" isLoading={isSubmitting} onClick={() => void handleRegenerate()}>
            Regenerate recovery codes
          </Button>
          <Button variant="danger" isLoading={isSubmitting} onClick={() => void handleDisable()}>
            Disable two-factor authentication
          </Button>
        </div>
      ) : setup ? (
        <div className="mt-4 flex flex-col gap-4">
          {/* QrCode::generate() output is server-generated SVG, not user input. */}
          <div
            className="w-fit rounded-md border border-neutral-200 bg-white p-3"
            dangerouslySetInnerHTML={{ __html: setup.qr_code_svg }}
          />
          <p className="text-xs text-neutral-500">
            Can't scan? Enter this code manually: <span className="font-mono">{setup.secret}</span>
          </p>
          <form className="flex items-end gap-3" onSubmit={(event) => void handleConfirm(event)} noValidate>
            <Input
              label="Confirmation code"
              value={confirmCode}
              onChange={(event) => setConfirmCode(event.target.value)}
              disabled={isSubmitting}
              className="max-w-[10rem]"
              autoFocus
            />
            <Button type="submit" isLoading={isSubmitting}>
              Confirm and enable
            </Button>
          </form>
        </div>
      ) : (
        <Button className="mt-4" isLoading={isSubmitting} onClick={() => void handleStart()}>
          Set up two-factor authentication
        </Button>
      )}
    </div>
  );
}
