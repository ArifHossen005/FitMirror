import {
  type DragEvent,
  forwardRef,
  type InputHTMLAttributes,
  useId,
  useRef,
  useState,
} from 'react';

import { cn } from '../utils/cn';

export type FileUploadStatus = 'pending' | 'uploading' | 'success' | 'error';

export interface FileUploadItem {
  id: string;
  file: File;
  progress: number;
  status: FileUploadStatus;
  error?: string;
}

export interface FileUploaderProps extends Omit<
  InputHTMLAttributes<HTMLInputElement>,
  'onChange' | 'type' | 'value'
> {
  items: FileUploadItem[];
  onFilesAdded: (files: File[]) => void;
  onRemove: (id: string) => void;
  /** Comma-separated MIME types / extensions, e.g. "image/png,image/jpeg" */
  accept?: string;
  multiple?: boolean;
  maxSizeBytes?: number;
  /** Called with files rejected client-side (wrong type or too large) before onFilesAdded runs. */
  onRejected?: (rejections: { file: File; reason: string }[]) => void;
  disabled?: boolean;
  hint?: string;
  className?: string;
}

function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
  const value = bytes / 1024 ** exponent;
  return `${exponent === 0 ? value : value.toFixed(1)} ${units[exponent]}`;
}

function matchesAccept(file: File, accept: string): boolean {
  const patterns = accept
    .split(',')
    .map((pattern) => pattern.trim())
    .filter(Boolean);
  if (patterns.length === 0) return true;

  return patterns.some((pattern) => {
    if (pattern.startsWith('.')) {
      return file.name.toLowerCase().endsWith(pattern.toLowerCase());
    }
    if (pattern.endsWith('/*')) {
      return file.type.startsWith(pattern.slice(0, -1));
    }
    return file.type === pattern;
  });
}

export const FileUploader = forwardRef<HTMLInputElement, FileUploaderProps>(
  (
    {
      items,
      onFilesAdded,
      onRemove,
      accept,
      multiple = true,
      maxSizeBytes,
      onRejected,
      disabled = false,
      hint,
      className,
      ...inputProps
    },
    ref,
  ) => {
    const [isDragging, setIsDragging] = useState(false);
    const dragCounter = useRef(0);
    const inputId = useId();

    const processFiles = (fileList: FileList | File[]) => {
      const files = Array.from(fileList);
      const accepted: File[] = [];
      const rejected: { file: File; reason: string }[] = [];

      for (const file of files) {
        if (accept && !matchesAccept(file, accept)) {
          rejected.push({ file, reason: 'unsupported-type' });
          continue;
        }
        if (maxSizeBytes && file.size > maxSizeBytes) {
          rejected.push({ file, reason: 'too-large' });
          continue;
        }
        accepted.push(file);
      }

      if (rejected.length > 0) onRejected?.(rejected);
      if (accepted.length > 0) onFilesAdded(multiple ? accepted : accepted.slice(0, 1));
    };

    const handleDragEnter = (event: DragEvent<HTMLLabelElement>) => {
      event.preventDefault();
      if (disabled) return;
      dragCounter.current += 1;
      setIsDragging(true);
    };

    const handleDragLeave = (event: DragEvent<HTMLLabelElement>) => {
      event.preventDefault();
      dragCounter.current -= 1;
      if (dragCounter.current <= 0) {
        dragCounter.current = 0;
        setIsDragging(false);
      }
    };

    const handleDragOver = (event: DragEvent<HTMLLabelElement>) => {
      event.preventDefault();
    };

    const handleDrop = (event: DragEvent<HTMLLabelElement>) => {
      event.preventDefault();
      dragCounter.current = 0;
      setIsDragging(false);
      if (disabled) return;
      if (event.dataTransfer.files.length > 0) processFiles(event.dataTransfer.files);
    };

    return (
      <div className={cn('flex flex-col gap-3', className)}>
        <label
          htmlFor={inputId}
          onDragEnter={handleDragEnter}
          onDragLeave={handleDragLeave}
          onDragOver={handleDragOver}
          onDrop={handleDrop}
          className={cn(
            'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-6 py-8 text-center transition-colors',
            isDragging
              ? 'border-brand-500 bg-brand-50'
              : 'hover:border-brand-400 hover:bg-brand-50/40 border-neutral-300 bg-neutral-50',
            disabled && 'pointer-events-none cursor-not-allowed opacity-50',
          )}
        >
          <svg
            className="h-8 w-8 text-neutral-400"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.5}
              d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 8.25 12 3.75m0 0L7.5 8.25M12 3.75v12"
            />
          </svg>
          <p className="text-sm font-medium text-neutral-700">
            <span className="text-brand-600">Click to upload</span> or drag and drop
          </p>
          {hint && <p className="text-xs text-neutral-400">{hint}</p>}
          <input
            {...inputProps}
            ref={ref}
            id={inputId}
            type="file"
            accept={accept}
            multiple={multiple}
            disabled={disabled}
            className="sr-only"
            onChange={(event) => {
              if (event.target.files && event.target.files.length > 0) {
                processFiles(event.target.files);
              }
              event.target.value = '';
            }}
          />
        </label>

        {items.length > 0 && (
          <ul className="flex flex-col gap-2">
            {items.map((item) => (
              <li
                key={item.id}
                className="flex items-center gap-3 rounded-md border border-neutral-200 px-3 py-2"
              >
                <div className="min-w-0 flex-1">
                  <div className="flex items-center justify-between gap-2">
                    <span className="truncate text-sm font-medium text-neutral-800">
                      {item.file.name}
                    </span>
                    <span className="shrink-0 text-xs text-neutral-400">
                      {formatBytes(item.file.size)}
                    </span>
                  </div>
                  {item.status === 'uploading' && (
                    <div
                      className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-neutral-100"
                      role="progressbar"
                      aria-valuenow={item.progress}
                      aria-valuemin={0}
                      aria-valuemax={100}
                    >
                      <div
                        className="bg-brand-500 h-full rounded-full transition-all"
                        style={{ width: `${Math.min(100, Math.max(0, item.progress))}%` }}
                      />
                    </div>
                  )}
                  {item.status === 'error' && item.error && (
                    <p className="text-danger-600 mt-1 text-xs">{item.error}</p>
                  )}
                </div>

                {item.status === 'success' && (
                  <svg
                    className="text-success-500 h-5 w-5 shrink-0"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-label="Upload complete"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="m4.5 12.75 6 6 9-13.5"
                    />
                  </svg>
                )}

                <button
                  type="button"
                  onClick={() => onRemove(item.id)}
                  aria-label={`Remove ${item.file.name}`}
                  className="shrink-0 rounded-md p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600"
                >
                  ✕
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    );
  },
);

FileUploader.displayName = 'FileUploader';
