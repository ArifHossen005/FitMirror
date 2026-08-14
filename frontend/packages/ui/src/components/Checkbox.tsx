import { forwardRef, type InputHTMLAttributes, useId } from 'react';

import { cn } from '../utils/cn';

export interface CheckboxProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label?: string;
  // See Input.tsx's InputProps for why this is `| undefined`, not just `?`.
  error?: string | undefined;
}

export const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(
  ({ className, label, error, id, ...props }, ref) => {
    const generatedId = useId();
    const checkboxId = id ?? generatedId;

    return (
      <div className="flex flex-col gap-1">
        <label htmlFor={checkboxId} className="inline-flex cursor-pointer items-center gap-2">
          <input
            ref={ref}
            type="checkbox"
            id={checkboxId}
            className={cn(
              'text-brand-500 h-4 w-4 rounded border-neutral-300',
              'focus:ring-brand-500/20 focus:outline-none focus:ring-2',
              'disabled:cursor-not-allowed disabled:opacity-50',
              className,
            )}
            aria-invalid={Boolean(error)}
            {...props}
          />
          {label && <span className="text-sm text-neutral-700">{label}</span>}
        </label>
        {error && <p className="text-danger-600 text-xs">{error}</p>}
      </div>
    );
  },
);

Checkbox.displayName = 'Checkbox';
