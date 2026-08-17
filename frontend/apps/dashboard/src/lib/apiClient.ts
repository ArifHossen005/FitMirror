import { createApiClient, getCurrentTenantSlug } from '@fitmirror/api';

import { logoutDashboardFromOutsideReact } from '../stores/dashboardAuthStore';

export const apiClient = createApiClient({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1',
  getTenantSlug: getCurrentTenantSlug,
  onUnauthorized: logoutDashboardFromOutsideReact,
});
