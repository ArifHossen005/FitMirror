import axios, { isAxiosError } from 'axios';

import { deviceToken, getDeviceFingerprint } from './deviceToken';

/** Mirrors App\Models\KioskDevice::PAIRING_CODE_LENGTH. */
export const PAIRING_CODE_LENGTH = 8;

/** Mirrors App\Models\KioskDevice::PAIRING_CODE_ALPHABET — no I, O, 0 or 1. */
export const PAIRING_CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

const BASE_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1';

export class KioskApiError extends Error {}

export interface KioskDeviceSummary {
  id: number;
  name: string;
  store_id: number;
  status: string;
  paired_at: string | null;
}

export interface KioskSettings {
  language: string;
  theme: string;
  idle_timeout_seconds: number;
  screensaver_playlist: string[];
  show_branding: boolean;
  attract_loop_enabled: boolean;
}

export interface KioskStore {
  id: number;
  name: string;
  code: string;
  city: string | null;
  area: string | null;
  phone: string | null;
  logo_url: string | null;
  banner_url: string | null;
  socials: Record<string, string>;
  timezone: string;
  status: string;
}

export interface KioskAvailability {
  is_open: boolean;
  timezone: string;
  local_time: string;
  next_opens_at: string | null;
}

export interface KioskConfig {
  device: KioskDeviceSummary;
  settings: KioskSettings;
  store: KioskStore | null;
  availability: KioskAvailability | null;
}

/**
 * A bare Axios instance, not the shared `apiClient`.
 *
 * The shared client attaches the *user* Sanctum token and calls
 * `onUnauthorized` — which logs the user out — on any 401. A kiosk has no
 * user session, and a 401 here means "this device is not paired", which
 * the pairing screen handles itself rather than being a logout. Reusing
 * the shared client would also send an unrelated staff token from another
 * tab as the kiosk's credential.
 */
const kioskHttp = axios.create({
  baseURL: BASE_URL,
  headers: { Accept: 'application/json' },
});

kioskHttp.interceptors.request.use((config) => {
  const token = deviceToken.get();

  if (token) config.headers.set('Authorization', `Bearer ${token}`);

  return config;
});

function messageFrom(error: unknown, fallback: string): string {
  if (isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(error)) {
    const body = error.response?.data;
    const firstFieldError = body?.errors ? Object.values(body.errors)[0]?.[0] : undefined;

    return firstFieldError ?? body?.message ?? fallback;
  }

  return fallback;
}

/** Normalises what a person types: lowercase letters and O/0 confusion are common. */
export function normalisePairingCode(input: string): string {
  return input
    .toUpperCase()
    .replace(/O/g, '0')
    .replace(/[^A-Z0-9]/g, '')
    .split('')
    .filter((character) => PAIRING_CODE_ALPHABET.includes(character))
    .join('')
    .slice(0, PAIRING_CODE_LENGTH);
}

/**
 * Redeems a pairing code for this device's long-lived token, and stores
 * it. The token is returned exactly once by the API and is never
 * recoverable afterwards, so it is persisted before this resolves.
 */
export async function claimPairingCode(code: string): Promise<KioskDeviceSummary> {
  try {
    const response = await kioskHttp.post('/kiosk/claim', {
      pairing_code: code,
      device_fingerprint: getDeviceFingerprint(),
      app_version: __APP_VERSION__,
    });

    const data = response.data.data as { device_token: string; device: KioskDeviceSummary };
    deviceToken.set(data.device_token);

    return data.device;
  } catch (error) {
    throw new KioskApiError(
      messageFrom(error, 'Could not pair this kiosk. Check the code and try again.'),
    );
  }
}

export async function fetchKioskConfig(): Promise<KioskConfig> {
  try {
    const response = await kioskHttp.get('/kiosk/config');
    return response.data.data as KioskConfig;
  } catch (error) {
    // A 401 means the device was unpaired from the dashboard. Clearing the
    // stored token here is what returns the app to the pairing screen on
    // its own, with no manual reset needed on the shop floor.
    if (isAxiosError(error) && error.response?.status === 401) {
      deviceToken.clear();
    }

    throw new KioskApiError(messageFrom(error, 'Could not reach FitMirror.'));
  }
}

export interface HeartbeatPayload {
  health?: {
    camera_ok?: boolean;
    network_ok?: boolean;
    storage_free_mb?: number;
    battery_percent?: number;
    last_error?: string | null;
  };
}

export async function sendHeartbeat(payload: HeartbeatPayload = {}): Promise<KioskSettings | null> {
  try {
    const response = await kioskHttp.post('/kiosk/heartbeat', {
      app_version: __APP_VERSION__,
      ...payload,
    });

    return (response.data.data as { settings: KioskSettings }).settings;
  } catch (error) {
    if (isAxiosError(error) && error.response?.status === 401) {
      deviceToken.clear();
    }

    return null;
  }
}
