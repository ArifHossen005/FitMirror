import type { ReactNode } from 'react';

import { cn } from '../utils/cn';

export interface EmptyStateProps {
  icon?: ReactNode;
  title: string;
  description?: string;
  action?: ReactNode;
  className?: string;
}

const defaultIcon = (
  <svg
    className="h-10 w-10 text-neutral-300"
    fill="none"
    viewBox="0 0 24 24"
    stroke="currentColor"
    aria-hidden="true"
  >
    <path
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth={1.5}
      d="M3.75 9.75h16.5M3.75 9.75v8.25A2.25 2.25 0 0 0 6 20.25h12a2.25 2.25 0 0 0 2.25-2.25V9.75M3.75 9.75l1.588-3.176A2.25 2.25 0 0 1 7.353 5.25h9.294a2.25 2.25 0 0 1 2.015 1.324L20.25 9.75"
    />
  </svg>
);

/** Shown wherever a list/table/panel has no data yet — never a bare blank screen. */
export function EmptyState({ icon, title, description, action, className }: EmptyStateProps) {
  return (
    <div
      className={cn(
        'flex flex-col items-center justify-center gap-3 px-6 py-12 text-center',
        className,
      )}
    >
      {icon ?? defaultIcon}
      <div className="flex flex-col gap-1">
        <p className="text-sm font-medium text-neutral-700">{title}</p>
        {description && <p className="max-w-sm text-sm text-neutral-400">{description}</p>}
      </div>
      {action}
    </div>
  );
}
