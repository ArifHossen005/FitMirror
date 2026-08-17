import { useState } from 'react';

import { exitImpersonation } from '../lib/auth';
import { useDashboardAuthStore } from '../stores/dashboardAuthStore';

/** Shown across the top of every page while this tab is running an impersonation session — see lib/impersonation.ts. */
export function ImpersonationBanner() {
  const impersonation = useDashboardAuthStore((state) => state.impersonation);
  const logout = useDashboardAuthStore((state) => state.logout);
  const [isExiting, setIsExiting] = useState(false);

  if (!impersonation) return null;

  async function handleExit() {
    setIsExiting(true);
    try {
      await exitImpersonation();
    } finally {
      logout();
      window.close();
      // window.close() is a no-op for a tab the user opened themselves
      // (only script-opened tabs can be closed programmatically) — logout()
      // above has already ended the local session either way, so this is
      // just a friendlier landing state than a bare redirect.
      setIsExiting(false);
    }
  }

  return (
    <div className="flex items-center justify-between gap-4 bg-amber-500 px-4 py-2 text-sm font-medium text-amber-950">
      <span>
        You're viewing this dashboard as a support session, impersonated by {impersonation.superAdminName}.
      </span>
      <button
        type="button"
        onClick={() => void handleExit()}
        disabled={isExiting}
        className="rounded-md bg-amber-950/10 px-3 py-1 font-semibold hover:bg-amber-950/20 disabled:opacity-50"
      >
        {isExiting ? 'Exiting…' : 'Exit impersonation'}
      </button>
    </div>
  );
}
