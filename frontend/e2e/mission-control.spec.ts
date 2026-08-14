import { expect, test } from '@playwright/test';

test('an unauthenticated visit to "/" redirects to the login page with no console errors', async ({
  page,
}) => {
  const consoleErrors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });

  await page.goto('/');

  await expect(page).toHaveURL(/\/login$/);
  await expect(page.getByRole('heading', { name: 'Mission Control' })).toBeVisible();
  expect(consoleErrors).toEqual([]);
});

test('the login form validates required fields client-side before submitting', async ({ page }) => {
  await page.goto('/login');

  // The submit label is localized (bn by default) — target the form's
  // submit button by role/type rather than by text so this test doesn't
  // depend on which locale is active.
  await page.locator('form button[type="submit"]').click();

  await expect(page.getByText('Email is required.')).toBeVisible();
  await expect(page.getByText('Password is required.')).toBeVisible();
});

test('unknown route falls back to the 404 page', async ({ page }) => {
  await page.goto('/this-route-does-not-exist');

  await expect(page.getByText('404')).toBeVisible();
});
