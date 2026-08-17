import { tokenStorage, useTenantStore } from '@fitmirror/api';
import { create } from 'zustand';
import { persist } from 'zustand/middleware';

/**
 * Deliberately its own store, not @fitmirror/api's generic useAuthStore —
 * that store's AuthUser is a minimal placeholder from Phase 1.B, predating
 * RBAC. This one carries the full GET /api/v1/auth/me shape (Phase 2.C:
 * roles, permissions) that every dashboard page built from here on needs.
 */
export interface DashboardUser {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  avatar: string | null;
  locale: 'bn' | 'en';
  status: string;
  emailVerified: boolean;
  twoFactorEnabled: boolean;
  isTenantOwner: boolean;
}

export interface DashboardTenant {
  id: number;
  name: string;
  slug: string;
  status: string;
}

interface DashboardAuthState {
  user: DashboardUser | null;
  tenant: DashboardTenant | null;
  roles: string[];
  permissions: string[];
  isAuthenticated: boolean;
  /**
   * Set only while this browser tab is running an impersonation session,
   * opened by Mission Control in a *separate* tab from the super admin's
   * own session (see ImpersonationBanner's docblock for the full flow) —
   * so "restoring the original session" never requires juggling two
   * tokens in one tab, only closing this one.
   */
  impersonation: { superAdminName: string } | null;
  setSession: (data: {
    user: DashboardUser;
    tenant: DashboardTenant | null;
    roles: string[];
    permissions: string[];
    token: string;
  }) => void;
  setMe: (data: { user: DashboardUser; tenant: DashboardTenant | null; roles: string[]; permissions: string[] }) => void;
  startImpersonation: (superAdminName: string) => void;
  logout: () => void;
}

export const useDashboardAuthStore = create<DashboardAuthState>()(
  persist(
    (set) => ({
      user: null,
      tenant: null,
      roles: [],
      permissions: [],
      isAuthenticated: false,
      impersonation: null,

      setSession: ({ user, tenant, roles, permissions, token }) => {
        tokenStorage.set(token);
        if (tenant) {
          useTenantStore.getState().setTenant({
            id: tenant.id,
            slug: tenant.slug,
            name: tenant.name,
            planSlug: 'free',
          });
        }
        set({ user, tenant, roles, permissions, isAuthenticated: true });
      },

      setMe: ({ user, tenant, roles, permissions }) => {
        set({ user, tenant, roles, permissions, isAuthenticated: true });
      },

      startImpersonation: (superAdminName) => {
        set({ impersonation: { superAdminName } });
      },

      logout: () => {
        tokenStorage.clear();
        useTenantStore.getState().clearTenant();
        set({ user: null, tenant: null, roles: [], permissions: [], isAuthenticated: false, impersonation: null });
      },
    }),
    {
      name: 'fitmirror.dashboard.auth',
      partialize: (state) => ({
        user: state.user,
        tenant: state.tenant,
        roles: state.roles,
        permissions: state.permissions,
        isAuthenticated: state.isAuthenticated,
        impersonation: state.impersonation,
      }),
    },
  ),
);

/** Non-hook accessor for use inside the Axios interceptor, which runs outside React. */
export function logoutDashboardFromOutsideReact(): void {
  useDashboardAuthStore.getState().logout();
}
