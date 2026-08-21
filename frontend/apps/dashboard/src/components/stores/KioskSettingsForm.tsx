import { Button, Modal, Select, Textarea } from '@fitmirror/ui';
import { useMutation } from '@tanstack/react-query';
import { type FormEvent, useState } from 'react';

import {
  ApiError,
  KIOSK_IDLE_TIMEOUT_MAX,
  KIOSK_IDLE_TIMEOUT_MIN,
  KIOSK_LANGUAGES,
  KIOSK_THEMES,
  type KioskDevice,
  type KioskSettings,
  updateKioskSettings,
} from '../../lib/stores';

export interface KioskSettingsFormProps {
  device: KioskDevice;
  onClose: () => void;
  onSaved: () => void;
}

/**
 * Display settings for one kiosk. Changes reach an unattended device on
 * its next heartbeat (60 seconds at most) — the heartbeat response carries
 * the current settings back, so no push channel or polling endpoint is
 * needed on the device side.
 */
export function KioskSettingsForm({ device, onClose, onSaved }: KioskSettingsFormProps) {
  const [settings, setSettings] = useState<KioskSettings>(device.settings);
  const [playlistText, setPlaylistText] = useState(device.settings.screensaver_playlist.join('\n'));
  const [error, setError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: (payload: Partial<KioskSettings>) => updateKioskSettings(device.id, payload),
    onSuccess: () => {
      setError(null);
      onSaved();
      onClose();
    },
    onError: (mutationError) => {
      setError(mutationError instanceof ApiError ? mutationError.message : 'Unable to save settings.');
    },
  });

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    setError(null);

    mutation.mutate({
      ...settings,
      // One URL per line, blanks dropped — the API rejects empty strings in
      // the playlist and there is no reason to make the user care.
      screensaver_playlist: playlistText
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== ''),
    });
  };

  return (
    <Modal isOpen onClose={onClose} title={`Display settings — ${device.name}`} size="lg">
      <form className="flex flex-col gap-4" onSubmit={handleSubmit} noValidate>
        <div className="grid gap-4 sm:grid-cols-2">
          <Select
            label="Language"
            value={settings.language}
            onChange={(event) => setSettings({ ...settings, language: event.target.value })}
            options={KIOSK_LANGUAGES}
            disabled={mutation.isPending}
          />
          <Select
            label="Theme"
            value={settings.theme}
            onChange={(event) => setSettings({ ...settings, theme: event.target.value })}
            options={KIOSK_THEMES}
            disabled={mutation.isPending}
          />
        </div>

        <label className="flex flex-col gap-1.5">
          <span className="text-sm font-medium text-neutral-700">
            Idle timeout — {settings.idle_timeout_seconds}s
          </span>
          <input
            type="range"
            min={KIOSK_IDLE_TIMEOUT_MIN}
            max={KIOSK_IDLE_TIMEOUT_MAX}
            step={15}
            value={settings.idle_timeout_seconds}
            onChange={(event) =>
              setSettings({ ...settings, idle_timeout_seconds: Number(event.target.value) })
            }
            disabled={mutation.isPending}
          />
          <span className="text-xs text-neutral-500">
            How long the kiosk waits before returning to the attract screen.
          </span>
        </label>

        <Textarea
          label="Screensaver playlist"
          rows={4}
          value={playlistText}
          onChange={(event) => setPlaylistText(event.target.value)}
          hint="One image or video URL per line. Shown while the kiosk is idle."
          disabled={mutation.isPending}
        />

        <div className="flex flex-col gap-2">
          <label className="flex items-center gap-2 text-sm text-neutral-700">
            <input
              type="checkbox"
              checked={settings.show_branding}
              onChange={(event) => setSettings({ ...settings, show_branding: event.target.checked })}
              disabled={mutation.isPending}
            />
            Show this branch's logo and banner
          </label>
          <label className="flex items-center gap-2 text-sm text-neutral-700">
            <input
              type="checkbox"
              checked={settings.attract_loop_enabled}
              onChange={(event) =>
                setSettings({ ...settings, attract_loop_enabled: event.target.checked })
              }
              disabled={mutation.isPending}
            />
            Play the attract loop when idle
          </label>
        </div>

        {error && (
          <p role="alert" className="text-danger-600 text-sm">
            {error}
          </p>
        )}

        <p className="text-xs text-neutral-500">
          Saved changes reach the device on its next check-in, within a minute.
        </p>

        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={onClose} disabled={mutation.isPending}>
            Cancel
          </Button>
          <Button type="submit" isLoading={mutation.isPending}>
            Save settings
          </Button>
        </div>
      </form>
    </Modal>
  );
}
