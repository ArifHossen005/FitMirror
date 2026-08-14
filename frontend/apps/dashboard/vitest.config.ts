import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

export default defineConfig({
  plugins: [react()],
  // Workspace packages resolve through node_modules symlinks, so Vite's
  // dependency optimizer treats them as pre-bundled deps and transforms
  // their JSX with esbuild's classic runtime instead of react's automatic
  // one — excluding them keeps @fitmirror/* on the same source-transform
  // path as the app's own files.
  optimizeDeps: { exclude: ['@fitmirror/ui', '@fitmirror/api', '@fitmirror/i18n'] },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: true,
    // Workspace packages resolve through a node_modules symlink, so
    // Vitest's SSR module loader treats them as external deps and
    // require()s them raw instead of running them through the Vite/React
    // transform pipeline — inlining forces the transform to apply.
    server: { deps: { inline: [/@fitmirror\//] } },
  },
});
