import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

export default defineConfig({
  plugins: [react()],
  optimizeDeps: { exclude: ['@fitmirror/ui', '@fitmirror/api', '@fitmirror/i18n'] },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: true,
    server: { deps: { inline: [/@fitmirror\//] } },
  },
});
