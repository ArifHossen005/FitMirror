import { defineConfig, devices } from '@playwright/test';

/**
 * One Playwright config drives all four apps' smoke tests — each project
 * pins a baseURL to that app's dev-server port (see package.json's
 * dev:<app> scripts) and each webServer entry boots exactly one app.
 * `reuseExistingServer` in local dev means `npm run e2e` after `npm run
 * dev:dashboard` etc. is already running just attaches instead of
 * double-booting.
 */
export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 2 : undefined,
  reporter: 'html',
  use: {
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'dashboard',
      testMatch: /dashboard\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], baseURL: 'http://localhost:5173' },
    },
    {
      name: 'kiosk',
      testMatch: /kiosk\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], baseURL: 'http://localhost:5174' },
    },
    {
      name: 'portal',
      testMatch: /portal\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], baseURL: 'http://localhost:5175' },
    },
    {
      name: 'mission-control',
      testMatch: /mission-control\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], baseURL: 'http://localhost:5176' },
    },
  ],
  webServer: [
    {
      command: 'npm run dev:dashboard',
      url: 'http://localhost:5173',
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
    },
    {
      command: 'npm run dev:kiosk',
      url: 'http://localhost:5174',
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
    },
    {
      command: 'npm run dev:portal',
      url: 'http://localhost:5175',
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
    },
    {
      command: 'npm run dev:mission-control',
      url: 'http://localhost:5176',
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
    },
  ],
});
