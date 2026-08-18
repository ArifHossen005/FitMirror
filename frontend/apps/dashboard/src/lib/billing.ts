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

/** Same normalization as lib/auth.ts's own unwrap() — duplicated rather than exported from there and reused, matching this codebase's existing per-module convention (lib/staff.ts also doesn't share auth.ts's helper). */
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

export type BillingCycle = 'monthly' | 'yearly';

export interface PublicPlan {
  id: number;
  name: string;
  slug: string;
  price_monthly: number;
  price_yearly: number;
  currency: string;
  trial_days: number;
  limits: Record<string, number | null>;
  features: Record<string, { enabled: boolean; tier: string | null }>;
}

export async function fetchPublicPlans(): Promise<PublicPlan[]> {
  return unwrap(apiClient.get('/plans'));
}

export interface PlanUsageRow {
  key: string;
  current: number | null;
  limit: number | null;
  unlimited: boolean;
}

export interface PlanUsage {
  plan: { id: number; name: string; slug: string };
  usage: PlanUsageRow[];
}

export async function fetchPlanUsage(): Promise<PlanUsage> {
  return unwrap(apiClient.get('/plan/usage'));
}

export interface CouponPreview {
  code: string;
  subtotal: number;
  discount: number;
  total_before_vat: number;
}

export async function previewCoupon(
  code: string,
  planId: number,
  billingCycle: BillingCycle,
): Promise<CouponPreview> {
  return unwrap(apiClient.post('/billing/coupon/preview', { code, plan_id: planId, billing_cycle: billingCycle }));
}

export interface PaymentInitiateResult {
  invoice_number: string;
  subtotal: number;
  discount: number;
  vat: number;
  amount: number;
  currency: string;
  gateway_url: string;
}

export async function initiatePayment(
  planId: number,
  billingCycle: BillingCycle,
  couponCode?: string,
): Promise<PaymentInitiateResult> {
  return unwrap(
    apiClient.post('/payment/initiate', {
      plan_id: planId,
      billing_cycle: billingCycle,
      coupon_code: couponCode || undefined,
    }),
  );
}

export interface SubscriptionSummary {
  id: number;
  status: string;
  auto_renew: boolean;
  cancelled_at: string | null;
}

export interface CurrentSubscription {
  id: number;
  plan_id: number;
  billing_cycle: BillingCycle;
  status: string;
  auto_renew: boolean;
  starts_at: string | null;
  trial_ends_at: string | null;
  ends_at: string | null;
  cancelled_at: string | null;
}

export async function fetchCurrentSubscription(): Promise<CurrentSubscription | null> {
  return unwrap(apiClient.get('/subscription'));
}

export async function cancelSubscription(immediately: boolean, reason?: string): Promise<SubscriptionSummary> {
  return unwrap(apiClient.post('/subscription/cancel', { immediately, reason: reason || undefined }));
}

export async function setAutoRenew(autoRenew: boolean): Promise<{ id: number; auto_renew: boolean }> {
  return unwrap(apiClient.patch('/subscription/auto-renew', { auto_renew: autoRenew }));
}

export interface InvoiceSummary {
  id: number;
  number: string;
  type: 'plan' | 'addon';
  subtotal: number;
  discount: number;
  vat: number;
  total: number;
  currency: string;
  status: string;
  issued_at: string | null;
  paid_at: string | null;
  downloadable: boolean;
}

export async function fetchInvoices(page: number): Promise<Paginated<InvoiceSummary>> {
  const response = await apiClient.get('/billing/invoices', { params: { page } });
  return response.data;
}

/**
 * Streams the PDF as a blob (the download route requires the Bearer
 * token, so a plain `<a href>` can't be used) and triggers a save via a
 * throwaway object URL + anchor click.
 */
export async function downloadInvoice(invoiceId: number, filename: string): Promise<void> {
  const response = await apiClient.get(`/billing/invoices/${invoiceId}/download`, { responseType: 'blob' });
  const url = window.URL.createObjectURL(response.data as Blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}

export interface BillingHistoryRow {
  type: 'payment' | 'refund';
  id: number;
  gateway: string | null;
  amount: number;
  currency: string | null;
  status: string;
  created_at: string | null;
}

export async function fetchBillingHistory(page: number): Promise<Paginated<BillingHistoryRow>> {
  const response = await apiClient.get('/billing/history', { params: { page } });
  return response.data;
}

export interface AddonSummary {
  id: number;
  code: string;
  name: string;
  description: string | null;
  type: string;
  price: number;
  currency: string;
  unit_amount: number;
}

export async function fetchAddons(): Promise<AddonSummary[]> {
  return unwrap(apiClient.get('/billing/addons'));
}

export interface AddonPurchaseResult {
  invoice_number: string;
  amount: number;
  currency: string;
  gateway_url: string;
}

export async function purchaseAddon(addonId: number, quantity: number): Promise<AddonPurchaseResult> {
  return unwrap(apiClient.post(`/billing/addons/${addonId}/purchase`, { quantity }));
}

export interface PlanLimitErrorDetails {
  limit: string;
  current: number;
  max: number;
  upgradeUrl: string;
}

/**
 * App\Support\PlanGateResponse::limitExceeded() is the one backend place
 * that builds this exact shape ({error_code: 'plan_limit_exceeded',
 * errors: {limit, current, max, upgrade_url}}), so every plan-limit 403
 * across the whole app — not just billing — parses the same way here.
 * Returns null for any other error, so callers can safely try this first
 * and fall back to a generic message.
 */
export function parsePlanLimitError(error: unknown): PlanLimitErrorDetails | null {
  if (!isAxiosError<{ error_code?: string; errors?: Record<string, unknown> }>(error)) return null;
  const body = error.response?.data;
  if (body?.error_code !== 'plan_limit_exceeded' || !body.errors) return null;

  return {
    limit: String(body.errors.limit),
    current: Number(body.errors.current),
    max: Number(body.errors.max),
    upgradeUrl: String(body.errors.upgrade_url),
  };
}

export interface FeatureUnavailableErrorDetails {
  feature: string;
  upgradeUrl: string;
}

/** Same reasoning as parsePlanLimitError(), for EnforcePlanFeature's 403 (App\Support\PlanGateResponse::featureUnavailable()). */
export function parseFeatureUnavailableError(error: unknown): FeatureUnavailableErrorDetails | null {
  if (!isAxiosError<{ error_code?: string; errors?: Record<string, unknown> }>(error)) return null;
  const body = error.response?.data;
  if (body?.error_code !== 'plan_feature_unavailable' || !body.errors) return null;

  return { feature: String(body.errors.feature), upgradeUrl: String(body.errors.upgrade_url) };
}
