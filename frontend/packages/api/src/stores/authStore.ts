import { create } from 'zustand';

import { tokenStorage } from '../tokenStorage';

/**
 * Minimal shape shared by every app's user — dashboard/kiosk/portal each
 * layer their own richer type on top once Phase 2.B's `GET /auth/me`
 * response shape is final. Kept intentionally loose here so this store
 * doesn't need to change when that response grows.
 */
export interface AuthUser {
  id: number;
  name: string;
  email: string;
  locale: 'bn' | 'en';
}

interface AuthState {
  user: AuthUser | null;
  isAuthenticated: boolean;
  setSession: (user: AuthUser, token: string, expiresAt?: string | null) => void;
  logout: () => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  isAuthenticated: false,

  setSession: (user, token, expiresAt = null) => {
    tokenStorage.set(token, expiresAt);
    set({ user, isAuthenticated: true });
  },

  logout: () => {
    tokenStorage.clear();
    set({ user: null, isAuthenticated: false });
  },
}));

/**
 * Non-hook accessor for use inside the Axios interceptor (client.ts),
 * which runs outside React and can't call useAuthStore() directly.
 */
export function logoutFromOutsideReact(): void {
  useAuthStore.getState().logout();
}
