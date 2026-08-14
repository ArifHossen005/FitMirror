import { createQueryClient } from '@fitmirror/api';
import { createI18n } from '@fitmirror/i18n';
import { AppShell, type AppShellNavItem, Toaster } from '@fitmirror/ui';
import { QueryClientProvider } from '@tanstack/react-query';
import { useState } from 'react';
import { I18nextProvider, useTranslation } from 'react-i18next';
import { BrowserRouter } from 'react-router-dom';

import { AppRoutes } from './routes';

/**
 * Grows one entry per phase as each module's pages land (Phase 4 stores,
 * Phase 5 catalog, Phase 7 campaigns, ...) — see routes/index.tsx for the
 * matching <Route> additions.
 */
function useDashboardNavItems(): AppShellNavItem[] {
  const { t } = useTranslation();
  return [{ label: t('nav.dashboard'), to: '/', end: true }];
}

function Shell() {
  const navItems = useDashboardNavItems();
  return (
    <AppShell navItems={navItems}>
      <AppRoutes />
    </AppShell>
  );
}

export default function App() {
  const [queryClient] = useState(createQueryClient);
  const [i18n] = useState(createI18n);

  return (
    <I18nextProvider i18n={i18n}>
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>
          <Shell />
          <Toaster />
        </BrowserRouter>
      </QueryClientProvider>
    </I18nextProvider>
  );
}
