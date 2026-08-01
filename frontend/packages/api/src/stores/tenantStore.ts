import { create } from 'zustand';
import { persist } from 'zustand/middleware';

/**
 * The active tenant context for the current browser session. Persisted to
 * localStorage (not just memory) so a kiosk that's paired to a store and
 * then reloaded — which happens often, kiosks run unattended for weeks —
 * doesn't lose its tenant binding.
 */
export interface TenantSummary {
  id: number;
  slug: string;
  name: string;
  planSlug: 'free' | 'pro' | 'max';
}

interface TenantState {
  tenant: TenantSummary | null;
  setTenant: (tenant: TenantSummary) => void;
  clearTenant: () => void;
}

export const useTenantStore = create<TenantState>()(
  persist(
    (set) => ({
      tenant: null,
      setTenant: (tenant) => set({ tenant }),
      clearTenant: () => set({ tenant: null }),
    }),
    { name: 'fitmirror.tenant' },
  ),
);

/** Non-hook accessor — used by createApiClient's getTenantSlug option. */
export function getCurrentTenantSlug(): string | null {
  return useTenantStore.getState().tenant?.slug ?? null;
}
