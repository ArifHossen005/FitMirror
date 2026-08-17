import { AppShell, type AppShellNavItem } from '@fitmirror/ui';
import { Navigate, Outlet, useLocation } from 'react-router-dom';

import { ImpersonationBanner } from '../components/ImpersonationBanner';
import { usePermissions } from '../hooks/usePermissions';
import { logout } from '../lib/auth';
import { useDashboardAuthStore } from '../stores/dashboardAuthStore';

interface GatedNavItem extends AppShellNavItem {
  /** Omit to show unconditionally; a matching permission hides the item entirely, not just disables it. */
  permission?: string;
}

const NAV_ITEMS: GatedNavItem[] = [
  { label: 'Dashboard', to: '/', end: true },
  { label: 'Team', to: '/staff', permission: 'staff.view' },
  { label: 'Activity Log', to: '/audit-log', permission: 'audit_log.view' },
  { label: 'Settings', to: '/settings' },
];

/** Paths reachable even while a required-action redirect would otherwise fire — see below. */
const ACTION_EXEMPT_PATHS = ['/settings/two-factor', '/settings/sessions', '/verify-email'];

/**
 * Every route except the standalone auth pages (login, register, etc.)
 * renders inside this layout. Enforces, in order: authenticated session →
 * verified email → active tenant → (for the literal tenant owner only)
 * 2FA enabled — mirroring the backend's own EnsureTenantIsActive/
 * EnsureTwoFactorIsEnabled middleware exactly, so the UI never lets a user
 * navigate somewhere the API would reject anyway.
 */
export function ProtectedLayout() {
  const location = useLocation();
  const { can } = usePermissions();
  const isAuthenticated = useDashboardAuthStore((state) => state.isAuthenticated);
  const user = useDashboardAuthStore((state) => state.user);
  const tenant = useDashboardAuthStore((state) => state.tenant);

  if (!isAuthenticated || !user) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  }

  const isExempt = ACTION_EXEMPT_PATHS.some((path) => location.pathname.startsWith(path));

  if (!isExempt) {
    if (!user.emailVerified) {
      return <Navigate to="/verify-email" replace />;
    }

    if (tenant && (tenant.status === 'pending' || tenant.status === 'rejected')) {
      return <Navigate to="/pending-approval" replace />;
    }

    if (user.isTenantOwner && !user.twoFactorEnabled) {
      return <Navigate to="/settings/two-factor" replace />;
    }
  }

  const navItems = NAV_ITEMS.filter((item) => !item.permission || can(item.permission));

  return (
    <div className="flex min-h-screen flex-col">
      <ImpersonationBanner />
      <AppShell
        navItems={navItems}
        user={{ name: user.name, email: user.email, ...(user.avatar ? { avatarUrl: user.avatar } : {}) }}
        onLogout={() => void logout()}
      >
        <Outlet />
      </AppShell>
    </div>
  );
}
