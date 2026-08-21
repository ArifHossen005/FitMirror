/**
 * The kiosk's own long-lived credential, and the stable fingerprint it
 * reports at pairing time.
 *
 * Deliberately separate from `@fitmirror/api`'s tokenStorage: that holds a
 * *user's* Sanctum token under its own key, and a kiosk has no user. The
 * device token authenticates the hardware itself against
 * App\Http\Middleware\AuthenticateKioskDevice and must survive reboots and
 * staff turnover, so it lives in localStorage under a key nothing else
 * touches — a stray logout in another tab must never unpair the kiosk.
 */
const TOKEN_KEY = 'fitmirror.kiosk.device_token';
const FINGERPRINT_KEY = 'fitmirror.kiosk.fingerprint';

export const deviceToken = {
  get(): string | null {
    return localStorage.getItem(TOKEN_KEY);
  },

  set(token: string): void {
    localStorage.setItem(TOKEN_KEY, token);
  },

  clear(): void {
    localStorage.removeItem(TOKEN_KEY);
  },

  isPaired(): boolean {
    return Boolean(localStorage.getItem(TOKEN_KEY));
  },
};

/**
 * A stable per-device identifier, generated once and then reused.
 *
 * Not a secret and never trusted for authentication — the server treats it
 * as a label so the dashboard can show which physical machine claimed a
 * pairing code. Generated randomly rather than derived from browser
 * characteristics: a real fingerprinting scheme would be both less stable
 * (a browser update changes the answer) and a privacy concern for no gain
 * here.
 */
export function getDeviceFingerprint(): string {
  const existing = localStorage.getItem(FINGERPRINT_KEY);

  if (existing) return existing;

  const generated =
    typeof crypto !== 'undefined' && 'randomUUID' in crypto
      ? crypto.randomUUID()
      : `kiosk-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;

  localStorage.setItem(FINGERPRINT_KEY, generated);

  return generated;
}
