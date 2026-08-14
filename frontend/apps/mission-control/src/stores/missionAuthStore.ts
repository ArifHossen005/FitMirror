import { tokenStorage } from '@fitmirror/api';
import { create } from 'zustand';
import { persist } from 'zustand/middleware';

/**
 * Deliberately its own store, not @fitmirror/api's useAuthStore — a
 * SuperAdmin's shape (role, permissions, status) has nothing in common
 * with the tenant-facing AuthUser type, and the two must never be
 * confusable at the type level given how strictly the backend separates
 * the `super_admin` guard from `sanctum` (see PROGRESS.md Phase 1.C).
 */
export interface MissionSuperAdmin {
  id: number;
  name: string;
  email: string;
  role: string;
  roleLabel: string;
  status: string;
}

interface MissionAuthState {
  superAdmin: MissionSuperAdmin | null;
  isAuthenticated: boolean;
  setSession: (superAdmin: MissionSuperAdmin, token: string) => void;
  logout: () => void;
}

export const useMissionAuthStore = create<MissionAuthState>()(
  persist(
    (set) => ({
      superAdmin: null,
      isAuthenticated: false,

      setSession: (superAdmin, token) => {
        tokenStorage.set(token);
        set({ superAdmin, isAuthenticated: true });
      },

      logout: () => {
        tokenStorage.clear();
        set({ superAdmin: null, isAuthenticated: false });
      },
    }),
    {
      name: 'fitmirror.mission.auth',
      // The token itself lives in tokenStorage (localStorage, shared shape
      // with the other three apps) — only the profile needed to render the
      // shell without a network round-trip on every reload is persisted
      // here.
      partialize: (state) => ({
        superAdmin: state.superAdmin,
        isAuthenticated: state.isAuthenticated,
      }),
    },
  ),
);

/** Non-hook accessor for use inside the Axios interceptor, which runs outside React. */
export function logoutMissionControlFromOutsideReact(): void {
  useMissionAuthStore.getState().logout();
}
