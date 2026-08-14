import { MissionShell, type MissionShellNavItem } from '@fitmirror/ui';
import { Navigate, Outlet, useLocation } from 'react-router-dom';

import { missionLogout } from '../lib/missionAuth';
import { useMissionAuthStore } from '../stores/missionAuthStore';

/** Grows as Phase 1.C and Phase 13 add tenants/plans/revenue/ops/support pages. */
const navItems: MissionShellNavItem[] = [{ label: 'Overview', to: '/', end: true }];

/**
 * Every route except /login renders inside this layout. An unauthenticated
 * visit redirects to /login and remembers the attempted path, so
 * MissionLogin can send the super admin back where they were headed.
 */
export function ProtectedLayout() {
  const location = useLocation();
  const superAdmin = useMissionAuthStore((state) => state.superAdmin);
  const isAuthenticated = useMissionAuthStore((state) => state.isAuthenticated);

  if (!isAuthenticated || !superAdmin) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  }

  return (
    <MissionShell
      navItems={navItems}
      pageTitle="Overview"
      user={{ name: superAdmin.name, email: superAdmin.email, role: superAdmin.roleLabel }}
      onLogout={() => void missionLogout()}
    >
      <Outlet />
    </MissionShell>
  );
}
