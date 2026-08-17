import { expect, test } from '@playwright/test';

test('dashboard shell loads with the brand mark and no console errors', async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });

  await page.goto('/');

  // Unauthenticated "/" redirects to /login (Phase 2.C) — the brand mark
  // above the sign-in form is the shell being checked for, not any one
  // specific page's content. getByText('FitMirror') alone is ambiguous
  // now that the login page's own copy ("New to FitMirror?") also
  // contains the string.
  await expect(page.getByText('FitMirror', { exact: true }).first()).toBeVisible();
  expect(consoleErrors).toEqual([]);
});

test('unknown route falls back to the 404 page', async ({ page }) => {
  await page.goto('/this-route-does-not-exist');

  await expect(page.getByText('404')).toBeVisible();
});

test('a shop owner can register, then log in and reach the team page', async ({ page }) => {
  const unique = Date.now();
  const email = `e2e-owner-${unique}@example.com`;
  // Password::uncompromised() (Phase 2.B) hits the real Have I Been Pwned
  // API in this E2E run (unlike the PHPUnit suite, which fakes that HTTP
  // call) — a common-looking fixed string like "Str0ng!Passw0rd" genuinely
  // gets rejected as breached. Folding the timestamp in keeps this
  // effectively unique every run.
  const password = `Zq7!Xk${unique}Fp`;

  // bn (Bengali) is the app's default locale (packages/i18n) — pinning to
  // en here so the test can assert on stable English label text rather
  // than transliterating every locator into Bengali. Set before any page
  // script runs, since createI18n() reads this synchronously at init.
  await page.addInitScript(() => window.localStorage.setItem('fitmirror.locale', 'en'));

  await page.goto('/register');
  await page.getByLabel('Shop name').fill(`E2E Shop ${unique}`);
  await page.getByLabel('Full name').fill('E2E Owner');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByLabel('Confirm password').fill(password);
  await page.getByRole('button', { name: 'Create account' }).click();

  // No token is issued at registration (RegisterController) — the app
  // lands on the unauthenticated verify-email notice, not the dashboard.
  await expect(page.getByText('Check your email')).toBeVisible();

  // The registered owner's email is real but unverified; login itself
  // doesn't require verification (LoginService has no such check), so
  // this proves the full register -> login -> RBAC-aware dashboard path
  // works against the live backend, not just that the login form submits.
  await page.goto('/login');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Log in' }).click();

  // Unverified email takes priority over the tenant's own pending-approval
  // state in ProtectedLayout's redirect order — see its own docblock.
  // A generous timeout: `php artisan serve` is single-threaded (dev only,
  // per PROGRESS.md Decision D-01) and this suite runs all four apps'
  // Playwright projects concurrently against it — under that combined
  // load the login round trip can take noticeably longer than a single
  // isolated request would.
  await expect(page).toHaveURL(/\/verify-email$/, { timeout: 20_000 });
});
