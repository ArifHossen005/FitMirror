import { create } from 'zustand';

export type ToastVariant = 'success' | 'error' | 'info' | 'warning';

export interface ToastItem {
  id: string;
  message: string;
  variant: ToastVariant;
  /** ms before auto-dismiss; 0 disables auto-dismiss (e.g. for errors needing an explicit ack). */
  duration: number;
}

interface ToastStoreState {
  toasts: ToastItem[];
  push: (message: string, variant: ToastVariant, duration: number) => void;
  dismiss: (id: string) => void;
}

export const useToastStore = create<ToastStoreState>((set) => ({
  toasts: [],
  push: (message, variant, duration) => {
    const id = crypto.randomUUID();

    set((state) => ({ toasts: [...state.toasts, { id, message, variant, duration }] }));

    if (duration > 0) {
      setTimeout(() => {
        set((state) => ({ toasts: state.toasts.filter((toast) => toast.id !== id) }));
      }, duration);
    }
  },
  dismiss: (id) => set((state) => ({ toasts: state.toasts.filter((toast) => toast.id !== id) })),
}));

/**
 * Imperative API — call `toast.success('Saved.')` from anywhere (a mutation
 * onSuccess handler, a catch block) without needing to be inside a
 * component that consumes a hook. Mirrors the ergonomics of libraries like
 * react-hot-toast/sonner, implemented directly to avoid another dependency
 * for what is a small amount of code.
 */
export const toast = {
  success: (message: string, duration = 4000) =>
    useToastStore.getState().push(message, 'success', duration),
  error: (message: string, duration = 6000) =>
    useToastStore.getState().push(message, 'error', duration),
  info: (message: string, duration = 4000) =>
    useToastStore.getState().push(message, 'info', duration),
  warning: (message: string, duration = 5000) =>
    useToastStore.getState().push(message, 'warning', duration),
};
