import { isAxiosError } from 'axios';

import { type MissionSuperAdmin, useMissionAuthStore } from '../stores/missionAuthStore';
import { apiClient } from './apiClient';

interface MissionLoginResponse {
  success: boolean;
  message: string;
  data: {
    token: string;
    super_admin: {
      id: number;
      name: string;
      email: string;
      role: string;
      status: string;
    };
  };
}

/**
 * POST /api/v1/mission/login (routes/api_mission.php, Phase 1.C). Throws a
 * plain string message on failure so the login page can render it directly
 * without knowing about Axios error shapes.
 */
export async function missionLogin(email: string, password: string): Promise<MissionSuperAdmin> {
  try {
    const response = await apiClient.post<MissionLoginResponse>('/login', { email, password });
    const { token, super_admin: superAdmin } = response.data.data;

    const profile: MissionSuperAdmin = {
      id: superAdmin.id,
      name: superAdmin.name,
      email: superAdmin.email,
      role: superAdmin.role,
      roleLabel: formatRoleLabel(superAdmin.role),
      status: superAdmin.status,
    };

    useMissionAuthStore.getState().setSession(profile, token);

    return profile;
  } catch (error) {
    throw new Error(extractErrorMessage(error));
  }
}

export async function missionLogout(): Promise<void> {
  try {
    await apiClient.post('/logout');
  } finally {
    useMissionAuthStore.getState().logout();
  }
}

function formatRoleLabel(role: string): string {
  return role
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

function extractErrorMessage(error: unknown): string {
  if (isAxiosError<{ message?: string }>(error) && error.response?.data?.message) {
    return error.response.data.message;
  }

  return 'Unable to reach Mission Control right now. Please try again.';
}
