import type { ReactNode } from 'react';
import { NavLink } from 'react-router-dom';

import { useUiStore } from '../stores/uiStore';
import { cn } from '../utils/cn';

export interface MissionShellNavItem {
  label: string;
  to: string;
  icon?: ReactNode;
  end?: boolean;
}

export interface MissionShellUser {
  name: string;
  email?: string;
  role: string;
}

export interface MissionShellProps {
  navItems: MissionShellNavItem[];
  pageTitle: string;
  user?: MissionShellUser | null;
  onLogout?: () => void;
  impersonationBanner?: ReactNode;
  children: ReactNode;
}

/**
 * Super-admin (Mission Control) shell. Deliberately styled darker/denser
 * than AppShell so the Product Owner can never mistake it for a tenant
 * dashboard, even in a screenshot.
 */
export function MissionShell({
  navItems,
  pageTitle,
  user,
  onLogout,
  impersonationBanner,
  children,
}: MissionShellProps) {
  const sidebarCollapsed = useUiStore((state) => state.sidebarCollapsed);
  const toggleSidebar = useUiStore((state) => state.toggleSidebar);

  return (
    <div className="flex min-h-screen bg-neutral-100">
      {impersonationBanner}

      <aside
        className={cn(
          'sticky top-0 flex h-screen shrink-0 flex-col bg-neutral-900 text-neutral-100 transition-[width] duration-200',
          sidebarCollapsed ? 'w-shell-sidebar-collapsed' : 'w-shell-sidebar',
        )}
      >
        <div className="h-shell-topbar flex shrink-0 items-center justify-center border-b border-neutral-800 px-3">
          {sidebarCollapsed ? (
            <span className="text-lg font-bold text-white">MC</span>
          ) : (
            <span className="text-sm font-semibold uppercase tracking-wide text-white">
              Mission Control
            </span>
          )}
        </div>
        <nav className="flex flex-1 flex-col gap-1 overflow-y-auto p-3" aria-label="Primary">
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end ?? false}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                  isActive
                    ? 'bg-neutral-800 text-white'
                    : 'text-neutral-400 hover:bg-neutral-800/60 hover:text-white',
                )
              }
              title={sidebarCollapsed ? item.label : undefined}
            >
              {item.icon && (
                <span className="shrink-0" aria-hidden="true">
                  {item.icon}
                </span>
              )}
              {!sidebarCollapsed && <span className="truncate">{item.label}</span>}
            </NavLink>
          ))}
        </nav>
        <button
          type="button"
          onClick={toggleSidebar}
          aria-label={sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          className="flex h-10 shrink-0 items-center justify-center border-t border-neutral-800 text-neutral-500 hover:bg-neutral-800 hover:text-white"
        >
          {sidebarCollapsed ? '»' : '«'}
        </button>
      </aside>

      <div className="flex min-h-screen flex-1 flex-col">
        <header className="h-shell-topbar sticky top-0 z-10 flex shrink-0 items-center justify-between border-b border-neutral-200 bg-white px-6">
          <h1 className="text-lg font-semibold text-neutral-900">{pageTitle}</h1>

          {user && (
            <div className="flex items-center gap-3">
              <div className="text-right leading-tight">
                <p className="text-sm font-medium text-neutral-800">{user.name}</p>
                <p className="text-xs uppercase tracking-wide text-neutral-400">{user.role}</p>
              </div>
              <div className="flex h-9 w-9 items-center justify-center rounded-full bg-neutral-900 text-sm font-semibold text-white">
                {user.name.charAt(0).toUpperCase()}
              </div>
              {onLogout && (
                <button
                  type="button"
                  onClick={onLogout}
                  className="hover:text-danger-600 text-sm font-medium text-neutral-500"
                >
                  Log out
                </button>
              )}
            </div>
          )}
        </header>

        <main className="flex-1 p-6">{children}</main>
      </div>
    </div>
  );
}
