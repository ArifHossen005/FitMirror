import { useQuery } from '@tanstack/react-query';
import { useCallback, useEffect, useState } from 'react';

import { deviceToken } from '../lib/deviceToken';
import { fetchKioskConfig, sendHeartbeat } from '../lib/pairing';
import { KioskPairingPage } from './KioskPairingPage';

/** Matches App\Models\KioskDevice::HEARTBEAT_INTERVAL_SECONDS. */
const HEARTBEAT_INTERVAL_MS = 60_000;

/**
 * The kiosk's root screen. Three states, in order:
 *
 *   1. Unpaired — show the pairing screen.
 *   2. Paired but outside its branch's kiosk hours — show a closed screen
 *      with the next opening time. The availability endpoint is
 *      deliberately outside the active-hours guard so this screen has real
 *      data to render rather than an error (see KioskSessionController).
 *   3. Paired and open — the try-on experience, built in Phase 6.
 *
 * The heartbeat runs in all three paired states, including while closed:
 * a device that stopped reporting in because the shop shut for the night
 * would show as offline in the owner's dashboard, which is not what
 * "offline" should mean.
 */
export function KioskHome() {
  const [isPaired, setIsPaired] = useState(() => deviceToken.isPaired());

  const configQuery = useQuery({
    queryKey: ['kiosk', 'config'],
    queryFn: fetchKioskConfig,
    enabled: isPaired,
    // Re-checked on the heartbeat cadence so an out-of-hours kiosk flips to
    // the try-on screen on its own when the branch opens, with nobody
    // touching it.
    refetchInterval: HEARTBEAT_INTERVAL_MS,
    retry: false,
  });

  // A 401 clears the stored token (see fetchKioskConfig), which means the
  // device was unpaired from the dashboard — drop back to the pairing
  // screen rather than retrying forever.
  useEffect(() => {
    if (configQuery.isError && !deviceToken.isPaired()) {
      setIsPaired(false);
    }
  }, [configQuery.isError]);

  const beat = useCallback(async () => {
    const cameraOk = await probeCamera();

    await sendHeartbeat({ health: { camera_ok: cameraOk, network_ok: navigator.onLine } });

    if (!deviceToken.isPaired()) setIsPaired(false);
  }, []);

  useEffect(() => {
    if (!isPaired) return;

    void beat();
    const interval = window.setInterval(() => void beat(), HEARTBEAT_INTERVAL_MS);

    return () => window.clearInterval(interval);
  }, [isPaired, beat]);

  if (!isPaired) {
    return <KioskPairingPage onPaired={() => setIsPaired(true)} />;
  }

  if (configQuery.isLoading) {
    return (
      <div className="flex flex-1 items-center justify-center">
        <p className="text-neutral-400">Connecting to FitMirror…</p>
      </div>
    );
  }

  const config = configQuery.data;

  if (!config) {
    return (
      <div className="flex flex-1 flex-col items-center justify-center gap-2 px-6 text-center">
        <p className="text-kiosk-lg font-medium">Cannot reach FitMirror</p>
        <p className="text-sm text-neutral-400">
          Check this device's internet connection. It will retry automatically.
        </p>
      </div>
    );
  }

  const { store, availability, settings } = config;

  if (availability && !availability.is_open) {
    return (
      <div className="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
        {settings.show_branding && store?.logo_url && (
          <img src={store.logo_url} alt="" className="h-20 w-20 rounded-lg object-cover" />
        )}
        <h1 className="text-kiosk-2xl font-semibold">{store?.name ?? 'FitMirror'}</h1>
        <p className="text-neutral-400">We're closed right now.</p>
        {availability.next_opens_at && (
          <p className="text-sm text-neutral-500">
            Opening {new Date(availability.next_opens_at).toLocaleString()}
          </p>
        )}
      </div>
    );
  }

  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
      {settings.show_branding && store?.logo_url && (
        <img src={store.logo_url} alt="" className="h-20 w-20 rounded-lg object-cover" />
      )}
      <h1 className="text-kiosk-2xl font-semibold">{store?.name ?? 'FitMirror'}</h1>
      <p className="text-neutral-400">
        Kiosk try-on experience — built out in Phase 6 (WebRTC + MediaPipe render pipeline).
      </p>
      <p className="text-xs text-neutral-600">
        Paired as {config.device.name} · {store?.code}
      </p>
    </div>
  );
}

/**
 * Whether the camera this kiosk's whole purpose depends on is actually
 * usable, reported on every heartbeat so a shop owner sees a broken kiosk
 * in the dashboard rather than discovering it from a customer.
 *
 * Uses the permissions API where available (a passive read that never
 * prompts). Where it is not — Safari has no 'camera' permission name — it
 * reports the presence of a video input device instead, which is weaker
 * but still catches an unplugged webcam without triggering a permission
 * dialog on an unattended screen.
 */
async function probeCamera(): Promise<boolean> {
  if (!navigator.mediaDevices) return false;

  try {
    const permissions = navigator.permissions as
      | { query?: (descriptor: { name: string }) => Promise<{ state: string }> }
      | undefined;

    if (permissions?.query) {
      const status = await permissions.query({ name: 'camera' });
      return status.state !== 'denied';
    }
  } catch {
    // 'camera' is not a recognised permission name in this browser; fall
    // through to the device enumeration below.
  }

  try {
    const devices = await navigator.mediaDevices.enumerateDevices();
    return devices.some((device) => device.kind === 'videoinput');
  } catch {
    return false;
  }
}
