import { Button, Input, Modal } from '@fitmirror/ui';
import { useMutation, useQuery } from '@tanstack/react-query';
import { type FormEvent, useEffect, useState } from 'react';

import {
  ApiError,
  fetchKioskDevice,
  type KioskDeviceWithCode,
  registerKioskDevice,
} from '../../lib/stores';

export interface KioskPairingModalProps {
  storeId: number;
  isOpen: boolean;
  /** Set when a code was minted elsewhere (regenerate/unpair) and only needs displaying. */
  existingDevice: KioskDeviceWithCode | null;
  onClose: () => void;
  onPaired: () => void;
}

/** How often the modal asks whether the device has claimed its code yet. */
const CLAIM_POLL_MS = 3000;

function secondsUntil(iso: string | null): number {
  if (!iso) return 0;
  return Math.max(0, Math.round((new Date(iso).getTime() - Date.now()) / 1000));
}

function formatCountdown(seconds: number): string {
  const minutes = Math.floor(seconds / 60);
  return `${minutes}:${String(seconds % 60).padStart(2, '0')}`;
}

/**
 * Two states in one modal: name the device, then show the code while the
 * device claims it.
 *
 * The claim status is polled rather than pushed. FitMirror has no
 * websocket layer yet, and a three-second poll for the couple of minutes a
 * staff member stands in front of the kiosk is far less machinery than
 * standing one up for this single screen. Polling stops the moment the
 * device pairs, the code expires, or the modal closes.
 */
export function KioskPairingModal({
  storeId,
  isOpen,
  existingDevice,
  onClose,
  onPaired,
}: KioskPairingModalProps) {
  const [name, setName] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [device, setDevice] = useState<KioskDeviceWithCode | null>(null);
  const [secondsLeft, setSecondsLeft] = useState(0);

  useEffect(() => {
    if (existingDevice) setDevice(existingDevice);
  }, [existingDevice]);

  useEffect(() => {
    if (!isOpen) {
      setDevice(null);
      setName('');
      setError(null);
    }
  }, [isOpen]);

  const registerMutation = useMutation({
    mutationFn: () => registerKioskDevice(storeId, { name: name.trim() }),
    onSuccess: (created) => {
      setError(null);
      setDevice(created);
      onPaired();
    },
    onError: (mutationError) => {
      setError(
        mutationError instanceof ApiError ? mutationError.message : 'Unable to register this kiosk.',
      );
    },
  });

  const claimQuery = useQuery({
    queryKey: ['kiosk-device', device?.id, 'claim-status'],
    queryFn: () => fetchKioskDevice(device?.id as number),
    enabled: isOpen && device !== null,
    refetchInterval: (query) =>
      query.state.data?.status === 'paired' ? false : CLAIM_POLL_MS,
  });

  const isPaired = claimQuery.data?.status === 'paired';

  useEffect(() => {
    if (isPaired) onPaired();
  }, [isPaired, onPaired]);

  // A local countdown rather than a value from the poll — the code's expiry
  // is a fixed instant, so ticking it client-side keeps the display honest
  // between the three-second refetches.
  useEffect(() => {
    if (!device || isPaired) return;

    const tick = () => setSecondsLeft(secondsUntil(device.pairing_code_expires_at));
    tick();

    const interval = window.setInterval(tick, 1000);
    return () => window.clearInterval(interval);
  }, [device, isPaired]);

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    setError(null);
    registerMutation.mutate();
  };

  const isExpired = device !== null && !isPaired && secondsLeft === 0;

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={device ? 'Pair this kiosk' : 'Add a kiosk'}>
      {!device ? (
        <form className="flex flex-col gap-4" onSubmit={handleSubmit} noValidate>
          <Input
            label="Device name"
            required
            placeholder="Front Desk"
            value={name}
            onChange={(event) => setName(event.target.value)}
            hint="Something a staff member would recognise on the shop floor."
            disabled={registerMutation.isPending}
          />

          {error && (
            <p role="alert" className="text-danger-600 text-sm">
              {error}
            </p>
          )}

          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" isLoading={registerMutation.isPending} disabled={name.trim() === ''}>
              Get pairing code
            </Button>
          </div>
        </form>
      ) : (
        <div className="flex flex-col gap-4">
          <p className="text-sm text-neutral-600">
            Open FitMirror on <strong>{device.name}</strong> and enter this code:
          </p>

          <div className="rounded-lg bg-neutral-900 py-6 text-center">
            <span className="font-mono text-4xl tracking-[0.3em] text-white">
              {device.pairing_code}
            </span>
          </div>

          {isPaired ? (
            <p className="text-success-700 text-sm font-medium">
              Paired. This kiosk is now connected to your branch.
            </p>
          ) : isExpired ? (
            <p className="text-danger-600 text-sm">
              This code has expired. Close this window and generate a new one.
            </p>
          ) : (
            <div className="flex items-center gap-2 text-sm text-neutral-500">
              <span
                aria-hidden
                className="bg-brand-500 h-2 w-2 animate-pulse rounded-full"
              />
              Waiting for the device… code expires in {formatCountdown(secondsLeft)}
            </div>
          )}

          <div className="flex justify-end">
            <Button type="button" variant={isPaired ? 'primary' : 'outline'} onClick={onClose}>
              {isPaired ? 'Done' : 'Close'}
            </Button>
          </div>
        </div>
      )}
    </Modal>
  );
}
