import type { ReactNode } from 'react';
import { NavLink } from 'react-router-dom';

import { useUiStore } from '../stores/uiStore';
import { cn } from '../utils/cn';

export interface AppShellNavItem {
  label: string;
  to: string;
  icon?: ReactNode;
  end?: boolean;
}

export interface AppShellBreadcrumb {
  label: string;
  to?: string;
}

export interface AppShellUser {
  name: string;
  email?: string;
  avatarUrl?: string;
}

export interface AppShellProps {
  navItems: AppShellNavItem[];
  breadcrumbs?: AppShellBreadcrumb[];
  user?: AppShellUser | null;
  onLogout?: () => void;
  logo?: ReactNode;
  headerActions?: ReactNode;
  children: ReactNode;
}

/**
 * Shop-owner dashboard shell — collapsible sidebar, topbar with breadcrumbs
 * and a user menu. Sidebar collapse state is persisted via useUiStore so it
 * survives navigation and reloads.
 */
export function AppShell({
  navItems,
  breadcrumbs,
  user,
  onLogout,
  logo,
  headerActions,
  children,
}: AppShellProps) {
  const sidebarCollapsed = useUiStore((state) => state.sidebarCollapsed);
  const toggleSidebar = useUiStore((state) => state.toggleSidebar);

  return (
    <div className="flex min-h-screen bg-neutral-50">
      <aside
        className={cn(
          'sticky top-0 flex h-screen shrink-0 flex-col border-r border-neutral-200 bg-white transition-[width] duration-200',
          sidebarCollapsed ? 'w-shell-sidebar-collapsed' : 'w-shell-sidebar',
        )}
      >
        <div className="h-shell-topbar flex shrink-0 items-center justify-center border-b border-neutral-200 px-3">
          {logo ?? <span className="text-brand-600 text-lg font-semibold">FitMirror</span>}
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
                    ? 'bg-brand-50 text-brand-700'
                    : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900',
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
          className="flex h-10 shrink-0 items-center justify-center border-t border-neutral-200 text-neutral-400 hover:bg-neutral-50 hover:text-neutral-600"
        >
          {sidebarCollapsed ? '»' : '«'}
        </button>
      </aside>

      <div className="flex min-h-screen flex-1 flex-col">
        <header className="h-shell-topbar sticky top-0 z-10 flex shrink-0 items-center justify-between border-b border-neutral-200 bg-white px-6">
          <nav aria-label="Breadcrumb">
            <ol className="flex items-center gap-2 text-sm text-neutral-500">
              {breadcrumbs?.map((crumb, index) => (
                <li key={`${crumb.label}-${index}`} className="flex items-center gap-2">
                  {index > 0 && <span aria-hidden="true">/</span>}
                  {crumb.to ? (
                    <NavLink to={crumb.to} className="hover:text-neutral-900">
                      {crumb.label}
                    </NavLink>
                  ) : (
                    <span className="font-medium text-neutral-900">{crumb.label}</span>
                  )}
                </li>
              ))}
            </ol>
          </nav>

          <div className="flex items-center gap-4">
            {headerActions}
            {user && (
              <div className="flex items-center gap-3">
                <div className="text-right leading-tight">
                  <p className="text-sm font-medium text-neutral-800">{user.name}</p>
                  {user.email && <p className="text-xs text-neutral-400">{user.email}</p>}
                </div>
                {user.avatarUrl ? (
                  <img
                    src={user.avatarUrl}
                    alt={user.name}
                    className="h-9 w-9 rounded-full object-cover"
                  />
                ) : (
                  <div className="bg-brand-100 text-brand-700 flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold">
                    {user.name.charAt(0).toUpperCase()}
                  </div>
                )}
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
          </div>
        </header>

        <main className="flex-1 p-6">{children}</main>
      </div>
    </div>
  );
}
