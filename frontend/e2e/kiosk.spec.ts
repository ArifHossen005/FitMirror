import { expect, test } from '@playwright/test';

test('kiosk shell boots full-screen with the brand mark and no console errors', async ({
  page,
}) => {
  const consoleErrors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });

  await page.goto('/');

  await expect(page.getByRole('heading', { name: 'FitMirror' })).toBeVisible();
  expect(consoleErrors).toEqual([]);
});

test('unknown route falls back to the kiosk 404 screen', async ({ page }) => {
  await page.goto('/this-route-does-not-exist');

  await expect(page.getByText('404')).toBeVisible();
});
