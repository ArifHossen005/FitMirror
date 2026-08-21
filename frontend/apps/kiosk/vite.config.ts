import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

import packageJson from './package.json' with { type: 'json' };

export default defineConfig({
  plugins: [react()],
  // The kiosk reports its build version on every heartbeat so the shop
  // owner's dashboard can show which devices are running an old build.
  // Injected at build time from package.json rather than read at runtime —
  // the browser has no way to read a file outside the bundle.
  define: {
    __APP_VERSION__: JSON.stringify(packageJson.version),
  },
  server: { port: 5174 },
  build: {
    assetsDir: 'assets',
    rollupOptions: {
      output: {
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
      },
    },
  },
});
