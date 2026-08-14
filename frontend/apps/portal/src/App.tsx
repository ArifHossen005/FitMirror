import { createQueryClient } from '@fitmirror/api';
import { createI18n } from '@fitmirror/i18n';
import { PortalShell, Toaster } from '@fitmirror/ui';
import { QueryClientProvider } from '@tanstack/react-query';
import { useState } from 'react';
import { I18nextProvider } from 'react-i18next';
import { BrowserRouter } from 'react-router-dom';

import { AppRoutes } from './routes';

/**
 * No tabItems yet — the bottom tab bar (Home / Wishlist / Loyalty /
 * Profile) is added once those pages exist (Phases 8-9). The try-on view
 * itself (Phase 6.C) renders full-bleed and will bypass PortalShell's
 * chrome via its own route-level layout.
 */
export default function App() {
  const [queryClient] = useState(createQueryClient);
  const [i18n] = useState(createI18n);

  return (
    <I18nextProvider i18n={i18n}>
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>
          <PortalShell>
            <AppRoutes />
          </PortalShell>
          <Toaster />
        </BrowserRouter>
      </QueryClientProvider>
    </I18nextProvider>
  );
}
