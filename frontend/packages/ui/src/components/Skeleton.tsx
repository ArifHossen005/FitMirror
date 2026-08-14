import type { CSSProperties } from 'react';

import { cn } from '../utils/cn';

export type SkeletonVariant = 'text' | 'circle' | 'rect';

export interface SkeletonProps {
  variant?: SkeletonVariant;
  width?: number | string;
  height?: number | string;
  className?: string;
  /** Number of stacked lines — only meaningful for variant="text". */
  lines?: number;
}

const variantClasses: Record<SkeletonVariant, string> = {
  text: 'rounded',
  circle: 'rounded-full',
  rect: 'rounded-md',
};

function Bone({
  width,
  height,
  className,
}: {
  width?: number | string;
  height?: number | string;
  className?: string;
}) {
  const style: CSSProperties = {};
  if (width !== undefined) style.width = width;
  if (height !== undefined) style.height = height;

  return (
    <div
      className={cn('animate-pulse bg-neutral-200', className)}
      style={style}
      aria-hidden="true"
    />
  );
}

/** Generic loading placeholder. Use while a query is pending — never show a blank panel. */
export function Skeleton({ variant = 'text', width, height, className, lines = 1 }: SkeletonProps) {
  if (variant === 'text' && lines > 1) {
    return (
      <div className="flex flex-col gap-2" role="status" aria-label="Loading">
        {Array.from({ length: lines }).map((_, index) => (
          <Bone
            key={index}
            width={index === lines - 1 ? (width ?? '75%') : (width ?? '100%')}
            height={height ?? '0.875rem'}
            className={cn(variantClasses.text, className)}
          />
        ))}
      </div>
    );
  }

  const defaultHeight =
    variant === 'circle' ? (width ?? '2.5rem') : variant === 'text' ? '0.875rem' : '1.5rem';
  const defaultWidth = variant === 'circle' ? (height ?? '2.5rem') : '100%';

  return (
    <div role="status" aria-label="Loading">
      <Bone
        width={width ?? defaultWidth}
        height={height ?? defaultHeight}
        className={cn(variantClasses[variant], className)}
      />
    </div>
  );
}

/** Convenience preset for card-shaped loading placeholders (product cards, list rows). */
export function SkeletonCard({ className }: { className?: string }) {
  return (
    <div className={cn('flex flex-col gap-3 rounded-lg border border-neutral-200 p-4', className)}>
      <Skeleton variant="rect" height="8rem" />
      <Skeleton variant="text" lines={2} />
      <div className="flex items-center gap-2">
        <Skeleton variant="circle" width="2rem" height="2rem" />
        <Skeleton variant="text" width="40%" />
      </div>
    </div>
  );
}

/** Convenience preset for table row loading placeholders. */
export function SkeletonTableRows({ rows = 5, columns = 4 }: { rows?: number; columns?: number }) {
  return (
    <div className="flex flex-col gap-3" role="status" aria-label="Loading table rows">
      {Array.from({ length: rows }).map((_, rowIndex) => (
        <div key={rowIndex} className="flex items-center gap-4">
          {Array.from({ length: columns }).map((_, colIndex) => (
            <Skeleton key={colIndex} variant="text" width={colIndex === 0 ? '20%' : '100%'} />
          ))}
        </div>
      ))}
    </div>
  );
}
