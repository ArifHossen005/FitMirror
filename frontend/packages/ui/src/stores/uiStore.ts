import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export type Theme = 'light' | 'dark' | 'system';

interface UiState {
  theme: Theme;
  setTheme: (theme: Theme) => void;

  /** Dashboard/Mission Control AppShell sidebar. Unused by Kiosk/Portal shells. */
  sidebarCollapsed: boolean;
  toggleSidebar: () => void;

  /** Global loading indicator for actions that block the whole screen (rare — most loading is per-component via TanStack Query). */
  isGlobalLoading: boolean;
  setGlobalLoading: (loading: boolean) => void;
}

export const useUiStore = create<UiState>()(
  persist(
    (set) => ({
      theme: 'system',
      setTheme: (theme) => set({ theme }),

      sidebarCollapsed: false,
      toggleSidebar: () => set((state) => ({ sidebarCollapsed: !state.sidebarCollapsed })),

      isGlobalLoading: false,
      setGlobalLoading: (isGlobalLoading) => set({ isGlobalLoading }),
    }),
    {
      name: 'fitmirror.ui',
      // Only theme/sidebar preferences persist — a stuck "loading" flag
      // must never survive a reload.
      partialize: (state) => ({ theme: state.theme, sidebarCollapsed: state.sidebarCollapsed }),
    },
  ),
);
