import { tokenStorage } from '@fitmirror/api';

import { useDashboardAuthStore } from '../stores/dashboardAuthStore';
import { fetchMe } from './auth';

const TOKEN_PARAM = 'impersonation_token';
const ADMIN_NAME_PARAM = 'impersonated_by';

/**
 * Mission Control opens impersonation in a *new browser tab* rather than
 * swapping the super admin's own session in place — `POST /api/v1/mission/
 * impersonate/{user}` returns a token, and the Mission Control UI (Phase
 * 13) opens `{FRONTEND_URL}/?impersonation_token=...&impersonated_by=...`.
 * This runs once at dashboard startup: if those params are present, the
 * token becomes this tab's session and the params are stripped from the
 * URL so a page refresh/share never leaks the token in the address bar.
 * The super admin's own Mission Control tab is never touched — "restoring
 * the original session" (PROGRESS.md Phase 2.C) is simply closing this
 * tab once ImpersonationBanner's exit button has run.
 */
export async function bootstrapImpersonationFromUrl(): Promise<void> {
  const params = new URLSearchParams(window.location.search);
  const token = params.get(TOKEN_PARAM);
  const superAdminName = params.get(ADMIN_NAME_PARAM);

  if (!token) return;

  params.delete(TOKEN_PARAM);
  params.delete(ADMIN_NAME_PARAM);
  const remaining = params.toString();
  window.history.replaceState(
    {},
    '',
    window.location.pathname + (remaining ? `?${remaining}` : '') + window.location.hash,
  );

  tokenStorage.set(token);
  await fetchMe();
  useDashboardAuthStore.getState().startImpersonation(superAdminName ?? 'a Mission Control admin');
}
