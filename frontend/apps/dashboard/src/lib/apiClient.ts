import { createApiClient, getCurrentTenantSlug, logoutFromOutsideReact } from '@fitmirror/api';

export const apiClient = createApiClient({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1',
  getTenantSlug: getCurrentTenantSlug,
  onUnauthorized: logoutFromOutsideReact,
});
