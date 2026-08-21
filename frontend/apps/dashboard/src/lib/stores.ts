import type { PaginationMeta } from '@fitmirror/ui';
import { isAxiosError } from 'axios';

import { apiClient } from './apiClient';
import { ApiError, type FieldErrors } from './auth';

export { ApiError };

interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
}

/** Same normalization as lib/auth.ts and lib/billing.ts — duplicated per module, matching this codebase's existing convention. */
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

export interface Paginated<T> {
  data: T[];
  meta: { current_page: number; per_page: number; total: number; last_page: number };
}

export function toPaginationMeta(paginated: Paginated<unknown> | undefined): PaginationMeta {
  if (!paginated) return { currentPage: 1, perPage: 20, total: 0, lastPage: 1 };

  return {
    currentPage: paginated.meta.current_page,
    perPage: paginated.meta.per_page,
    total: paginated.meta.total,
    lastPage: paginated.meta.last_page,
  };
}

/* -------------------------------------------------------------------------- */
/* Stores                                                                     */
/* -------------------------------------------------------------------------- */

export const STORE_STATUSES = [
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Temporarily inactive' },
  { value: 'closed', label: 'Permanently closed' },
] as const;

/** Mirrors App\Models\Store::SUPPORTED_SOCIALS exactly — the backend rejects any other key. */
export const SUPPORTED_SOCIALS = [
  'facebook',
  'instagram',
  'tiktok',
  'youtube',
  'whatsapp',
  'website',
] as const;

export type SocialNetwork = (typeof SUPPORTED_SOCIALS)[number];

export interface Store {
  id: number;
  name: string;
  code: string;
  is_main: boolean;
  phone: string | null;
  email: string | null;
  address: string | null;
  city: string | null;
  area: string | null;
  lat: number | null;
  lng: number | null;
  map_url: string | null;
  logo_url: string | null;
  banner_url: string | null;
  socials: Partial<Record<SocialNetwork, string>>;
  timezone: string;
  status: string;
  status_label: string;
  kiosk_device_count: number;
  created_at: string | null;
}

export interface BranchLimit {
  used: number;
  /** null means unlimited — see PlanLimit's own docblock on the backend. */
  max: number | null;
  can_create_more: boolean;
}

export interface StoreListResponse {
  stores: Store[];
  meta: { current_page: number; per_page: number; total: number; last_page: number };
  branch_limit: BranchLimit;
}

export interface StorePayload {
  name: string;
  code: string;
  is_main?: boolean;
  phone?: string | null;
  email?: string | null;
  address?: string | null;
  city?: string | null;
  area?: string | null;
  lat?: number | null;
  lng?: number | null;
  map_url?: string | null;
  status?: string;
  socials?: Partial<Record<SocialNetwork, string>>;
}

export async function fetchStores(page: number): Promise<StoreListResponse> {
  return unwrap(apiClient.get('/stores', { params: { page } }));
}

export async function fetchStore(storeId: number): Promise<Store> {
  return unwrap(apiClient.get(`/stores/${storeId}`));
}

export async function createStore(payload: StorePayload): Promise<Store> {
  return unwrap(apiClient.post('/stores', payload));
}

export async function updateStore(storeId: number, payload: Partial<StorePayload>): Promise<Store> {
  return unwrap(apiClient.patch(`/stores/${storeId}`, payload));
}

export async function deleteStore(storeId: number): Promise<void> {
  await apiClient.delete(`/stores/${storeId}`);
}

/**
 * POST + multipart, never PATCH — PHP only parses a multipart body on
 * POST, so a PATCH upload arrives at the server with no files at all.
 */
export async function updateStoreBranding(
  storeId: number,
  payload: { logo?: Blob; banner?: Blob; removeLogo?: boolean; removeBanner?: boolean },
): Promise<Store> {
  const form = new FormData();

  if (payload.logo) form.append('logo', payload.logo, 'logo.png');
  if (payload.banner) form.append('banner', payload.banner, 'banner.png');
  if (payload.removeLogo) form.append('remove_logo', '1');
  if (payload.removeBanner) form.append('remove_banner', '1');

  return unwrap(apiClient.post(`/stores/${storeId}/branding`, form));
}

/* -------------------------------------------------------------------------- */
/* Opening hours                                                              */
/* -------------------------------------------------------------------------- */

export interface StoreHourDay {
  day_of_week: number;
  day_name: string;
  is_closed: boolean;
  /** "HH:MM:SS" as stored, or null. The editor works in "HH:MM". */
  opens_at: string | null;
  closes_at: string | null;
  kiosk_opens_at: string | null;
  kiosk_closes_at: string | null;
}

export interface KioskAvailability {
  is_open: boolean;
  timezone: string;
  local_time: string;
  next_opens_at: string | null;
}

export interface StoreHours {
  store_id: number;
  timezone: string;
  is_configured: boolean;
  days: StoreHourDay[];
  kiosk_availability: KioskAvailability;
}

export interface StoreHourPayloadDay {
  day_of_week: number;
  is_closed: boolean;
  opens_at?: string | null;
  closes_at?: string | null;
  kiosk_opens_at?: string | null;
  kiosk_closes_at?: string | null;
}

export async function fetchStoreHours(storeId: number): Promise<StoreHours> {
  return unwrap(apiClient.get(`/stores/${storeId}/hours`));
}

export async function updateStoreHours(
  storeId: number,
  days: StoreHourPayloadDay[],
): Promise<StoreHours> {
  return unwrap(apiClient.put(`/stores/${storeId}/hours`, { days }));
}

/** "09:00:00" -> "09:00". The API stores seconds; every control here is HH:MM. */
export function toInputTime(value: string | null): string {
  return value ? value.slice(0, 5) : '';
}

/* -------------------------------------------------------------------------- */
/* Kiosk devices                                                              */
/* -------------------------------------------------------------------------- */

export interface KioskSettings {
  language: string;
  theme: string;
  idle_timeout_seconds: number;
  screensaver_playlist: string[];
  show_branding: boolean;
  attract_loop_enabled: boolean;
}

export interface KioskDevice {
  id: number;
  store_id: number;
  name: string;
  status: string;
  status_label: string;
  is_online: boolean;
  device_fingerprint: string | null;
  app_version: string | null;
  paired_at: string | null;
  last_seen_at: string | null;
  last_seen_ip: string | null;
  health: Record<string, unknown> | null;
  settings: KioskSettings;
  has_pending_code: boolean;
  pairing_code_expires_at: string | null;
  created_at: string | null;
}

/** Only the two endpoints that mint a code return it — the list never does. */
export interface KioskDeviceWithCode extends KioskDevice {
  pairing_code: string;
}

export const KIOSK_LANGUAGES = [
  { value: 'bn', label: 'বাংলা (Bangla)' },
  { value: 'en', label: 'English' },
];

export const KIOSK_THEMES = [
  { value: 'light', label: 'Light' },
  { value: 'dark', label: 'Dark' },
];

export const KIOSK_IDLE_TIMEOUT_MIN = 15;
export const KIOSK_IDLE_TIMEOUT_MAX = 1800;

export async function fetchKioskDevices(
  storeId: number,
  page: number,
): Promise<Paginated<KioskDevice>> {
  const response = await apiClient.get(`/stores/${storeId}/kiosk-devices`, { params: { page } });
  return response.data;
}

export async function fetchKioskDevice(deviceId: number): Promise<KioskDevice> {
  return unwrap(apiClient.get(`/kiosk-devices/${deviceId}`));
}

export async function registerKioskDevice(
  storeId: number,
  payload: { name: string },
): Promise<KioskDeviceWithCode> {
  return unwrap(apiClient.post(`/stores/${storeId}/kiosk-devices`, payload));
}

export async function regenerateKioskPairingCode(deviceId: number): Promise<KioskDeviceWithCode> {
  return unwrap(apiClient.post(`/kiosk-devices/${deviceId}/pairing-code`));
}

export async function unpairKioskDevice(deviceId: number): Promise<KioskDeviceWithCode> {
  return unwrap(apiClient.post(`/kiosk-devices/${deviceId}/unpair`));
}

export async function suspendKioskDevice(deviceId: number): Promise<KioskDevice> {
  return unwrap(apiClient.post(`/kiosk-devices/${deviceId}/suspend`));
}

export async function reactivateKioskDevice(deviceId: number): Promise<KioskDevice> {
  return unwrap(apiClient.post(`/kiosk-devices/${deviceId}/reactivate`));
}

export async function updateKioskSettings(
  deviceId: number,
  settings: Partial<KioskSettings>,
): Promise<{ device_id: number; settings: KioskSettings }> {
  return unwrap(apiClient.put(`/kiosk-devices/${deviceId}/settings`, settings));
}

export async function deleteKioskDevice(deviceId: number): Promise<void> {
  await apiClient.delete(`/kiosk-devices/${deviceId}`);
}

/* -------------------------------------------------------------------------- */
/* Shifts                                                                     */
/* -------------------------------------------------------------------------- */

export interface Shift {
  id: number;
  store_id: number;
  store_name: string | null;
  user_id: number;
  staff_name: string | null;
  staff_email: string | null;
  shift_date: string;
  starts_at: string;
  ends_at: string;
  is_overnight: boolean;
  duration_minutes: number;
  status: string;
  status_label: string;
  note: string | null;
  created_at: string | null;
}

export interface Schedule {
  from: string;
  to: string;
  shifts: Shift[];
}

export async function fetchSchedule(params: {
  from: string;
  to: string;
  storeId?: number | undefined;
  userId?: number | undefined;
}): Promise<Schedule> {
  return unwrap(
    apiClient.get('/shifts/schedule', {
      params: {
        from: params.from,
        to: params.to,
        ...(params.storeId ? { store_id: params.storeId } : {}),
        ...(params.userId ? { user_id: params.userId } : {}),
      },
    }),
  );
}

export async function createShift(
  storeId: number,
  payload: { user_id: number; shift_date: string; starts_at: string; ends_at: string; note?: string },
): Promise<Shift> {
  return unwrap(apiClient.post(`/stores/${storeId}/shifts`, payload));
}

export async function updateShift(
  shiftId: number,
  payload: Partial<{ user_id: number; shift_date: string; starts_at: string; ends_at: string; note: string }>,
): Promise<Shift> {
  return unwrap(apiClient.patch(`/shifts/${shiftId}`, payload));
}

export async function cancelShift(shiftId: number): Promise<Shift> {
  return unwrap(apiClient.post(`/shifts/${shiftId}/cancel`));
}

export async function deleteShift(shiftId: number): Promise<void> {
  await apiClient.delete(`/shifts/${shiftId}`);
}

/* -------------------------------------------------------------------------- */
/* Subdomain & custom domain                                                  */
/* -------------------------------------------------------------------------- */

export interface SubdomainInfo {
  subdomain: string | null;
  url: string;
  custom_domain: string | null;
}

export interface SubdomainCheck {
  available: boolean;
  subdomain: string;
  reason: string | null;
  url: string | null;
}

export interface DnsInstruction {
  type: string;
  name: string;
  value: string;
  ttl: number;
}

export interface CustomDomain {
  id: number;
  domain: string;
  status: string;
  status_label: string;
  is_verified: boolean;
  dns: DnsInstruction;
  attempts: number;
  verified_at: string | null;
  last_checked_at: string | null;
  last_error: string | null;
}

export async function fetchSubdomain(): Promise<SubdomainInfo> {
  return unwrap(apiClient.get('/tenant/subdomain'));
}

export async function checkSubdomain(subdomain: string): Promise<SubdomainCheck> {
  return unwrap(apiClient.get('/tenant/subdomain/check', { params: { subdomain } }));
}

export async function assignSubdomain(subdomain: string): Promise<{ subdomain: string; url: string }> {
  return unwrap(apiClient.post('/tenant/subdomain', { subdomain }));
}

export async function fetchCustomDomain(): Promise<{
  request: CustomDomain | null;
  active_domain: string | null;
}> {
  return unwrap(apiClient.get('/tenant/custom-domain'));
}

export async function requestCustomDomain(domain: string): Promise<CustomDomain> {
  return unwrap(apiClient.post('/tenant/custom-domain', { domain }));
}

export async function verifyCustomDomain(): Promise<CustomDomain> {
  return unwrap(apiClient.post('/tenant/custom-domain/verify'));
}

export async function removeCustomDomain(): Promise<void> {
  await apiClient.delete('/tenant/custom-domain');
}
