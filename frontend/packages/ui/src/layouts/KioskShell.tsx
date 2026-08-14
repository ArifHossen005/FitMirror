import type { ReactNode } from 'react';

import { cn } from '../utils/cn';

export interface KioskShellProps {
  children: ReactNode;
  /** Fixed banner/countdown slot rendered above the main content (campaign banners, Phase 7). */
  topBanner?: ReactNode;
  /** Idle/screensaver overlay — rendered on top of everything when present. */
  overlay?: ReactNode;
  className?: string;
}

/**
 * Full-screen, chrome-free layout for the in-store kiosk app. No sidebar,
 * no browser affordances — the tablet/kiosk hardware is the only frame the
 * customer sees. `user-select: none` and disabled long-press context menus
 * keep the touch experience feeling like a native app, not a webpage.
 */
export function KioskShell({ children, topBanner, overlay, className }: KioskShellProps) {
  return (
    <div
      className={cn(
        'fixed inset-0 flex flex-col overflow-hidden bg-neutral-950 text-white',
        'select-none [-webkit-touch-callout:none]',
        className,
      )}
      onContextMenu={(event) => event.preventDefault()}
    >
      {topBanner}
      <div className="p-kiosk-safe flex flex-1 flex-col overflow-hidden">{children}</div>
      {overlay}
    </div>
  );
}
