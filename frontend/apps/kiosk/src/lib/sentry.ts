import * as Sentry from '@sentry/react';

/**
 * No-ops when VITE_SENTRY_DSN is unset (local dev by default) so the SDK
 * never spams the console or phones home without an explicit DSN.
 */
export function initSentry(): void {
  const dsn = import.meta.env.VITE_SENTRY_DSN;
  if (!dsn) return;

  Sentry.init({
    dsn,
    environment: import.meta.env.MODE,
    integrations: [Sentry.browserTracingIntegration()],
    tracesSampleRate: import.meta.env.MODE === 'production' ? 0.2 : 1.0,
  });
}
