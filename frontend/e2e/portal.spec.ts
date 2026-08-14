import { expect, test } from '@playwright/test';

test('portal shell loads with the brand mark and no console errors', async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });

  await page.goto('/');

  await expect(page.getByRole('heading', { name: 'FitMirror' })).toBeVisible();
  expect(consoleErrors).toEqual([]);
});

test('QR try-on landing route resolves the token from the URL', async ({ page }) => {
  await page.goto('/try-on/demo-token-123');

  await expect(page.getByText('demo-token-123')).toBeVisible();
});

test('unknown route falls back to the 404 page', async ({ page }) => {
  await page.goto('/this-route-does-not-exist');

  await expect(page.getByText('404')).toBeVisible();
});
