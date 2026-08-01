/**
 * Sanctum issues a plain bearer token (no built-in refresh token), so
 * "token storage" here is a single access token plus its expiry hint.
 * Kept in localStorage (not memory) so a page reload doesn't force a
 * re-login — this is a standalone SPA per app, not a single-page session
 * that lives for the tab's lifetime only.
 */
const STORAGE_KEY = 'fitmirror.auth.token';

export interface StoredToken {
  token: string;
  /** ISO 8601 expiry, when the backend provides one. */
  expiresAt: string | null;
}

export const tokenStorage = {
  get(): StoredToken | null {
    const raw = localStorage.getItem(STORAGE_KEY);

    if (!raw) return null;

    try {
      return JSON.parse(raw) as StoredToken;
    } catch {
      localStorage.removeItem(STORAGE_KEY);
      return null;
    }
  },

  set(token: string, expiresAt: string | null = null): void {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ token, expiresAt } satisfies StoredToken));
  },

  clear(): void {
    localStorage.removeItem(STORAGE_KEY);
  },

  isExpired(): boolean {
    const stored = tokenStorage.get();

    if (!stored?.expiresAt) return false;

    return new Date(stored.expiresAt).getTime() <= Date.now();
  },
};
