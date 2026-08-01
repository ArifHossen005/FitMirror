import axios, { type AxiosInstance, type InternalAxiosRequestConfig } from 'axios';

import { tokenStorage } from './tokenStorage';

/**
 * Local dev serves every app from a distinct localhost port rather than a
 * tenant subdomain, so the backend can't resolve the tenant from the Host
 * header the way it will in production (`{slug}.fitmirror.com`). This
 * header is the local/staging stand-in — ResolveTenant middleware
 * (Phase 2.A) checks it before falling back to subdomain resolution, so
 * the same client code works unmodified in both environments.
 */
const TENANT_HEADER = 'X-Tenant';

export interface ApiClientOptions {
  baseURL: string;
  /** Called once, after a 401 survives the refresh attempt — clear app-level auth state here. */
  onUnauthorized?: () => void;
  /**
   * Attempts to obtain a fresh token, e.g. by calling a future
   * `POST /auth/refresh`. Returns the new token, or null/throws to signal
   * refresh isn't possible — the interceptor then falls through to
   * onUnauthorized. Left undefined until Sanctum-side refresh exists
   * (see PROGRESS.md Phase 2.B); a bare 401 currently logs out directly.
   */
  refreshToken?: () => Promise<string | null>;
  getTenantSlug?: () => string | null;
}

/**
 * Creates a tenant-scoped, auth-aware Axios instance. Each app (dashboard,
 * kiosk, portal, mission-control) calls this once with its own baseURL and
 * gets independent interceptor state — there is deliberately no shared
 * singleton instance across apps, since each is a separate SPA.
 */
export function createApiClient(options: ApiClientOptions): AxiosInstance {
  const client = axios.create({
    baseURL: options.baseURL,
    headers: {
      Accept: 'application/json',
    },
  });

  client.interceptors.request.use((config: InternalAxiosRequestConfig) => {
    const stored = tokenStorage.get();

    if (stored?.token) {
      config.headers.set('Authorization', `Bearer ${stored.token}`);
    }

    const tenantSlug = options.getTenantSlug?.();

    if (tenantSlug) {
      config.headers.set(TENANT_HEADER, tenantSlug);
    }

    return config;
  });

  // Single-flight refresh guard — concurrent 401s from a burst of requests
  // (e.g. a page mounting several queries at once) share one refresh
  // attempt instead of each firing its own.
  let refreshInFlight: Promise<string | null> | null = null;

  client.interceptors.response.use(
    (response) => response,
    async (error) => {
      const originalRequest = error.config as
        | (InternalAxiosRequestConfig & {
            _retried?: boolean;
          })
        | undefined;

      const isUnauthorized = error.response?.status === 401;

      if (!isUnauthorized || !originalRequest || originalRequest._retried) {
        if (isUnauthorized) {
          tokenStorage.clear();
          options.onUnauthorized?.();
        }

        return Promise.reject(error);
      }

      originalRequest._retried = true;

      if (!options.refreshToken) {
        tokenStorage.clear();
        options.onUnauthorized?.();

        return Promise.reject(error);
      }

      try {
        refreshInFlight ??= options.refreshToken().finally(() => {
          refreshInFlight = null;
        });

        const newToken = await refreshInFlight;

        if (!newToken) {
          throw new Error('Token refresh returned no token');
        }

        tokenStorage.set(newToken);
        originalRequest.headers.set('Authorization', `Bearer ${newToken}`);

        return client(originalRequest);
      } catch {
        tokenStorage.clear();
        options.onUnauthorized?.();

        return Promise.reject(error);
      }
    },
  );

  return client;
}
