import { forwardRef, type InputHTMLAttributes, useId } from 'react';

import { cn } from '../utils/cn';

export interface RadioProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label?: string;
}

export const Radio = forwardRef<HTMLInputElement, RadioProps>(
  ({ className, label, id, ...props }, ref) => {
    const generatedId = useId();
    const radioId = id ?? generatedId;

    return (
      <label htmlFor={radioId} className="inline-flex cursor-pointer items-center gap-2">
        <input
          ref={ref}
          type="radio"
          id={radioId}
          className={cn(
            'text-brand-500 h-4 w-4 border-neutral-300',
            'focus:ring-brand-500/20 focus:outline-none focus:ring-2',
            'disabled:cursor-not-allowed disabled:opacity-50',
            className,
          )}
          {...props}
        />
        {label && <span className="text-sm text-neutral-700">{label}</span>}
      </label>
    );
  },
);

Radio.displayName = 'Radio';

export interface RadioGroupOption {
  value: string;
  label: string;
  disabled?: boolean;
}

export interface RadioGroupProps {
  name: string;
  value: string;
  onChange: (value: string) => void;
  options: RadioGroupOption[];
  label?: string;
  className?: string;
}

export function RadioGroup({ name, value, onChange, options, label, className }: RadioGroupProps) {
  return (
    <fieldset className={cn('flex flex-col gap-2', className)}>
      {label && <legend className="mb-1 text-sm font-medium text-neutral-700">{label}</legend>}
      {options.map((option) => (
        <Radio
          key={option.value}
          name={name}
          value={option.value}
          label={option.label}
          disabled={option.disabled}
          checked={value === option.value}
          onChange={() => onChange(option.value)}
        />
      ))}
    </fieldset>
  );
}
