import { createQueryClient } from '@fitmirror/api';
import { createI18n } from '@fitmirror/i18n';
import { Toaster } from '@fitmirror/ui';
import { QueryClientProvider } from '@tanstack/react-query';
import { useState } from 'react';
import { I18nextProvider } from 'react-i18next';
import { BrowserRouter } from 'react-router-dom';

import { AppRoutes } from './routes';

export default function App() {
  const [queryClient] = useState(createQueryClient);
  const [i18n] = useState(createI18n);

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
