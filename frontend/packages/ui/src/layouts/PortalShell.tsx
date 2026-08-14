import type { ReactNode } from 'react';
import { NavLink } from 'react-router-dom';

import { cn } from '../utils/cn';

export interface PortalTabItem {
  label: string;
  to: string;
  icon: ReactNode;
  end?: boolean;
}

export interface PortalShellProps {
  children: ReactNode;
  title?: string;
  onBack?: () => void;
  headerActions?: ReactNode;
  /** Bottom tab bar items (Home / Wishlist / Loyalty / Profile). Omit to hide the tab bar entirely (e.g. the try-on view). */
  tabItems?: PortalTabItem[];
  className?: string;
}

/**
 * Mobile-first shell for the customer portal (QR-scanned try-on, wishlist,
 * loyalty card, appointments). Centers content on wider viewports rather
 * than stretching it — this is a phone experience even when opened on a
 * desktop browser.
 */
export function PortalShell({
  children,
  title,
  onBack,
  headerActions,
  tabItems,
  className,
}: PortalShellProps) {
  return (
    <div className="flex min-h-screen justify-center bg-neutral-100">
      <div className="flex min-h-screen w-full max-w-md flex-col bg-white shadow-sm">
        {title !== undefined && (
          <header className="sticky top-0 z-10 flex h-14 shrink-0 items-center gap-3 border-b border-neutral-200 bg-white px-4">
            {onBack && (
              <button
                type="button"
                onClick={onBack}
                aria-label="Go back"
                className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-neutral-500 hover:bg-neutral-100"
              >
                ←
              </button>
            )}
            <h1 className="flex-1 truncate text-base font-semibold text-neutral-900">{title}</h1>
            {headerActions}
          </header>
        )}

        <main className={cn('flex-1 overflow-y-auto', className)}>{children}</main>

        {tabItems && tabItems.length > 0 && (
          <nav
            className="sticky bottom-0 z-10 flex shrink-0 border-t border-neutral-200 bg-white"
            aria-label="Primary"
          >
            {tabItems.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end ?? false}
                className={({ isActive }) =>
                  cn(
                    'flex flex-1 flex-col items-center gap-1 py-2 text-xs font-medium',
                    isActive ? 'text-brand-600' : 'text-neutral-400',
                  )
                }
              >
                <span className="h-5 w-5" aria-hidden="true">
                  {item.icon}
                </span>
                {item.label}
              </NavLink>
            ))}
          </nav>
        )}
      </div>
    </div>
  );
}
