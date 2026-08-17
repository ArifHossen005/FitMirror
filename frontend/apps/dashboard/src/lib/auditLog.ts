import { apiClient } from './apiClient';
import type { Paginated } from './staff';

export interface AuditLogEntry {
  id: number;
  module: string | null;
  action: string | null;
  description: string;
  causer: { id: number; name: string | null; email: string | null } | null;
  subject_type: string | null;
  subject_id: number | null;
  changes: { attributes?: Record<string, unknown>; old?: Record<string, unknown> };
  created_at: string | null;
}

export interface AuditLogFilters {
  page: number;
  user_id?: number | undefined;
  module?: string | undefined;
  action?: string | undefined;
  date_from?: string | undefined;
  date_to?: string | undefined;
}

export async function fetchAuditLog(filters: AuditLogFilters): Promise<Paginated<AuditLogEntry>> {
  const response = await apiClient.get('/audit-log', { params: filters });
  return response.data;
}
