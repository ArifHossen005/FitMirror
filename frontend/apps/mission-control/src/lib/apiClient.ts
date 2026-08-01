import { createApiClient, logoutFromOutsideReact } from '@fitmirror/api';

/**
 * Mission Control talks to /api/v1/mission, a separate guard from the
 * tenant API — it authenticates as a super admin, not a tenant user, so
 * unlike the other three apps' clients this one never sends a tenant
 * header (see routes/api_mission.php, Phase 1.C).
 */
export const apiClient = createApiClient({
  baseURL: import.meta.env.VITE_MISSION_API_URL ?? 'http://localhost:8000/api/v1/mission',
  onUnauthorized: logoutFromOutsideReact,
});
