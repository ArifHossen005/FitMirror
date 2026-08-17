import { isAxiosError } from 'axios';

import {
  type DashboardTenant,
  type DashboardUser,
  useDashboardAuthStore,
} from '../stores/dashboardAuthStore';
import { apiClient } from './apiClient';

interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface FieldErrors {
  [field: string]: string[];
}

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly fieldErrors: FieldErrors = {},
  ) {
    super(message);
  }
}

/** Wraps any apiClient call, normalizing Laravel's validation-error envelope into ApiError. */
async function unwrap<T>(promise: Promise<{ data: ApiEnvelope<T> }>): Promise<T> {
  try {
    const response = await promise;
    return response.data.data;
  } catch (error) {
    if (isAxiosError<{ message?: string; errors?: FieldErrors }>(error)) {
      throw new ApiError(
        error.response?.data?.message ?? 'Something went wrong. Please try again.',
        error.response?.data?.errors ?? {},
      );
    }
    throw new ApiError('Unable to reach the server. Please check your connection.');
  }
}

export interface RegisterPayload {
  tenant_name: string;
  name: string;
  email: string;
  phone?: string | undefined;
  password: string;
  password_confirmation: string;
}

export async function register(
  payload: RegisterPayload,
): Promise<{ tenant: { id: number; name: string; slug: string; status: string }; user: { id: number; name: string; email: string } }> {
  return unwrap(apiClient.post('/auth/register', payload));
}

type LoginResult =
  | { status: 'two_factor_required'; two_factor_token: string }
  | { status: 'authenticated'; token: string; user: { id: number; name: string; email: string; tenant_id: number | null } };

export async function login(email: string, password: string): Promise<LoginResult> {
  const result = await unwrap<LoginResult>(apiClient.post('/auth/login', { email, password }));

  if (result.status === 'authenticated') {
    await hydrateSessionFromToken(result.token);
  }

  return result;
}

export async function completeTwoFactorChallenge(
  twoFactorToken: string,
  code?: string,
  recoveryCode?: string,
): Promise<void> {
  const result = await unwrap<{ token: string }>(
    apiClient.post('/auth/2fa/challenge', {
      two_factor_token: twoFactorToken,
      code: code || undefined,
      recovery_code: recoveryCode || undefined,
    }),
  );

  await hydrateSessionFromToken(result.token);
}

/**
 * After login/2FA issues a raw token, this fetches GET /auth/me with that
 * token attached to populate the full session (tenant, roles, permissions)
 * before committing it to the store — so the store's `isAuthenticated`
 * flag only flips once the whole session shape is known, not just the bare
 * token.
 */
async function hydrateSessionFromToken(token: string): Promise<void> {
  const meResponse = await apiClient.get<ApiEnvelope<MeResponse>>('/auth/me', {
    headers: { Authorization: `Bearer ${token}` },
  });
  const me = meResponse.data.data;

  useDashboardAuthStore.getState().setSession({
    user: mapUser(me.user),
    tenant: me.tenant ? mapTenant(me.tenant) : null,
    roles: me.roles,
    permissions: me.permissions,
    token,
  });
}

interface MeResponse {
  user: {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    avatar: string | null;
    locale: 'bn' | 'en';
    status: string;
    email_verified: boolean;
    two_factor_enabled: boolean;
    is_tenant_owner: boolean;
    last_login_at: string | null;
  };
  tenant: { id: number; name: string; slug: string; status: string } | null;
  roles: string[];
  permissions: string[];
}

function mapUser(user: MeResponse['user']): DashboardUser {
  return {
    id: user.id,
    name: user.name,
    email: user.email,
    phone: user.phone,
    avatar: user.avatar,
    locale: user.locale,
    status: user.status,
    emailVerified: user.email_verified,
    twoFactorEnabled: user.two_factor_enabled,
    isTenantOwner: user.is_tenant_owner,
  };
}

function mapTenant(tenant: NonNullable<MeResponse['tenant']>): DashboardTenant {
  return { id: tenant.id, name: tenant.name, slug: tenant.slug, status: tenant.status };
}

export async function fetchMe(): Promise<void> {
  const me = await unwrap<MeResponse>(apiClient.get('/auth/me'));
  useDashboardAuthStore.getState().setMe({
    user: mapUser(me.user),
    tenant: me.tenant ? mapTenant(me.tenant) : null,
    roles: me.roles,
    permissions: me.permissions,
  });
}

export async function logout(): Promise<void> {
  try {
    await apiClient.post('/auth/logout');
  } finally {
    useDashboardAuthStore.getState().logout();
  }
}

export async function forgotPassword(email: string): Promise<string> {
  const response = await apiClient.post<ApiEnvelope<null>>('/auth/forgot-password', { email });
  return response.data.message;
}

export async function resetPassword(payload: {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}): Promise<string> {
  try {
    const response = await apiClient.post<ApiEnvelope<null>>('/auth/reset-password', payload);
    return response.data.message;
  } catch (error) {
    if (isAxiosError<{ message?: string }>(error)) {
      throw new ApiError(error.response?.data?.message ?? 'Password reset failed.');
    }
    throw error;
  }
}

export async function changePassword(payload: {
  current_password: string;
  new_password: string;
  new_password_confirmation: string;
}): Promise<void> {
  await unwrap(apiClient.post('/auth/change-password', payload));
}

export async function resendVerificationEmail(): Promise<string> {
  const response = await apiClient.post<ApiEnvelope<null>>('/auth/email/resend');
  return response.data.message;
}

export interface UpdateProfilePayload {
  name?: string | undefined;
  phone?: string | null | undefined;
  avatar?: File | undefined;
  locale?: 'bn' | 'en' | undefined;
}

export async function updateProfile(payload: UpdateProfilePayload): Promise<void> {
  const formData = new FormData();
  formData.append('_method', 'PATCH');
  if (payload.name !== undefined) formData.append('name', payload.name);
  if (payload.phone !== undefined && payload.phone !== null) formData.append('phone', payload.phone);
  if (payload.locale !== undefined) formData.append('locale', payload.locale);
  if (payload.avatar) formData.append('avatar', payload.avatar);

  await unwrap(apiClient.post('/auth/profile', formData, { headers: { 'Content-Type': 'multipart/form-data' } }));
  await fetchMe();
}

export interface SessionSummary {
  id: number;
  name: string;
  is_current: boolean;
  last_used_at: string | null;
  created_at: string | null;
}

export async function fetchSessions(): Promise<SessionSummary[]> {
  return unwrap(apiClient.get('/auth/sessions'));
}

export async function revokeSession(tokenId: number): Promise<void> {
  await apiClient.delete(`/auth/sessions/${tokenId}`);
}

export interface TwoFactorSetup {
  secret: string;
  otpauth_url: string;
  qr_code_svg: string;
}

export async function startTwoFactorSetup(): Promise<TwoFactorSetup> {
  return unwrap(apiClient.post('/auth/2fa/enable'));
}

export async function confirmTwoFactorSetup(code: string): Promise<string[]> {
  const result = await unwrap<{ recovery_codes: string[] }>(apiClient.post('/auth/2fa/confirm', { code }));
  await fetchMe();
  return result.recovery_codes;
}

export async function disableTwoFactor(): Promise<void> {
  await unwrap(apiClient.post('/auth/2fa/disable'));
  await fetchMe();
}

export async function regenerateRecoveryCodes(): Promise<string[]> {
  const result = await unwrap<{ recovery_codes: string[] }>(apiClient.post('/auth/2fa/recovery-codes'));
  return result.recovery_codes;
}

export async function acceptStaffInvitation(payload: {
  token: string;
  name: string;
  password: string;
  password_confirmation: string;
}): Promise<void> {
  const result = await unwrap<{
    token: string;
    user: { id: number; name: string; email: string };
    tenant: { id: number; name: string; slug: string };
  }>(apiClient.post('/auth/invitations/accept', payload));

  await hydrateSessionFromToken(result.token);
}

export async function exitImpersonation(): Promise<void> {
  await apiClient.post('/auth/impersonation/exit');
}

/** Flattens ApiError.fieldErrors (string[] per field) into the first message per field. */
export function mapFieldErrors(error: unknown): Record<string, string> {
  if (!(error instanceof ApiError)) return {};

  const mapped: Record<string, string> = {};
  for (const [field, messages] of Object.entries(error.fieldErrors)) {
    mapped[field] = messages[0] ?? error.message;
  }
  return mapped;
}

export { ApiError as AuthApiError };
