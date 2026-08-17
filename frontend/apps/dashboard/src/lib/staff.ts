import type { PaginationMeta } from '@fitmirror/ui';

import { apiClient } from './apiClient';

export interface StaffMember {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  avatar: string | null;
  status: string;
  is_owner: boolean;
  roles: string[];
  last_login_at: string | null;
  created_at: string | null;
}

export interface StaffInvitation {
  id: number;
  email: string;
  name: string | null;
  role: string;
  invited_by: string | null;
  expires_at: string;
  created_at: string | null;
}

export interface Paginated<T> {
  data: T[];
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}

/**
 * DataTable's `pagination` prop is optional (`pagination?: PaginationMeta`,
 * no `| undefined` in its own declaration) — under exactOptionalPropertyTypes
 * that means "omit the prop entirely or pass a real value", never an
 * explicit `undefined`, which a `data ? {...} : undefined` ternary produces
 * and TS then refuses at every call site. Returning a harmless "page 1 of
 * 1" placeholder while `paginated` isn't loaded yet sidesteps that
 * entirely — DataTable only renders its pager controls when
 * `lastPage > 1`, so this is inert until real data arrives.
 */
export function toPaginationMeta(paginated: Paginated<unknown> | undefined): PaginationMeta {
  if (!paginated) return { currentPage: 1, perPage: 20, total: 0, lastPage: 1 };

  return {
    currentPage: paginated.meta.current_page,
    perPage: paginated.meta.per_page,
    total: paginated.meta.total,
    lastPage: paginated.meta.last_page,
  };
}

export const INVITABLE_ROLES = ['manager', 'staff'] as const;

export async function fetchStaff(page: number): Promise<Paginated<StaffMember>> {
  const response = await apiClient.get('/staff', { params: { page } });
  return response.data;
}

export async function fetchPendingInvitations(page: number): Promise<Paginated<StaffInvitation>> {
  const response = await apiClient.get('/staff/invitations', { params: { page } });
  return response.data;
}

export async function inviteStaff(payload: { name?: string | undefined; email: string; role: string }): Promise<void> {
  await apiClient.post('/staff/invitations', payload);
}

export async function revokeInvitation(invitationId: number): Promise<void> {
  await apiClient.delete(`/staff/invitations/${invitationId}`);
}

export async function updateStaffRole(userId: number, role: string): Promise<void> {
  await apiClient.patch(`/staff/${userId}/role`, { role });
}

export async function deactivateStaff(userId: number): Promise<void> {
  await apiClient.post(`/staff/${userId}/deactivate`);
}

export async function reactivateStaff(userId: number): Promise<void> {
  await apiClient.post(`/staff/${userId}/reactivate`);
}

export async function deleteStaff(userId: number): Promise<void> {
  await apiClient.delete(`/staff/${userId}`);
}
