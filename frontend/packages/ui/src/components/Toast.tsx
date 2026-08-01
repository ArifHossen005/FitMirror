import { createPortal } from 'react-dom';

import type { ToastVariant } from '../stores/toastStore';
import { useToastStore } from '../stores/toastStore';
import { cn } from '../utils/cn';

const variantClasses: Record<ToastVariant, string> = {
  success: 'bg-success-500 text-white',
  error: 'bg-danger-500 text-white',
  info: 'bg-info-500 text-white',
  warning: 'bg-warning-500 text-white',
};

/** Mount once near the root of each app (see AppShell / main.tsx). */
export function Toaster() {
  const toasts = useToastStore((state) => state.toasts);
  const dismiss = useToastStore((state) => state.dismiss);

  if (toasts.length === 0) return null;

  return createPortal(
    <div
      className="fixed bottom-4 right-4 z-[100] flex w-full max-w-sm flex-col gap-2"
      role="region"
      aria-label="Notifications"
    >
      {toasts.map((item) => (
        <div
          key={item.id}
          role="status"
          className={cn(
            'flex items-start justify-between gap-3 rounded-md px-4 py-3 text-sm shadow-lg',
            variantClasses[item.variant],
          )}
        >
          <span>{item.message}</span>
          <button
            type="button"
            onClick={() => dismiss(item.id)}
            aria-label="Dismiss notification"
            className="shrink-0 opacity-80 hover:opacity-100"
          >
            ✕
          </button>
        </div>
      ))}
    </div>,
    document.body,
  );
}
