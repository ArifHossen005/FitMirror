import { createQueryClient, tokenStorage } from '@fitmirror/api';
import { createI18n } from '@fitmirror/i18n';
import { Toaster } from '@fitmirror/ui';
import { QueryClientProvider } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { I18nextProvider } from 'react-i18next';
import { BrowserRouter } from 'react-router-dom';

import { fetchMe } from './lib/auth';
import { bootstrapImpersonationFromUrl } from './lib/impersonation';
import { AppRoutes } from './routes';
import { useDashboardAuthStore } from './stores/dashboardAuthStore';

/**
 * Runs once before the route tree renders: picks up an impersonation
 * token from the URL if present (lib/impersonation.ts), then — whether or
 * not that ran — refreshes the session from GET /auth/me if a token is
 * stored, so a reload always reflects the *current* roles/permissions/
 * tenant status rather than whatever was last persisted to localStorage.
 * A stale persisted session is still shown immediately (no blank screen);
 * this only reconciles it in the background.
 */
function useSessionBootstrap() {
  const [isReady, setIsReady] = useState(false);

  useEffect(() => {
    let cancelled = false;

    async function bootstrap() {
      await bootstrapImpersonationFromUrl();

      if (tokenStorage.get()) {
        try {
          await fetchMe();
        } catch {
          useDashboardAuthStore.getState().logout();
        }
      }

      if (!cancelled) setIsReady(true);
    }

    void bootstrap();

    return () => {
      cancelled = true;
    };
  }, []);

  return isReady;
}

export default function App() {
  const [queryClient] = useState(createQueryClient);
  const [i18n] = useState(createI18n);
  const isReady = useSessionBootstrap();

  if (!isReady) {
    return <div className="flex min-h-screen items-center justify-center bg-neutral-50" />;
  }

  return (
    <I18nextProvider i18n={i18n}>
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>
          <AppRoutes />
          <Toaster />
        </BrowserRouter>
      </QueryClientProvider>
    </I18nextProvider>
  );
}
