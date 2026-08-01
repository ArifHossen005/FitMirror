import { QueryClient } from '@tanstack/react-query';

/**
 * Shared TanStack Query defaults. `staleTime` of 30s means a query
 * revisited within that window (e.g. navigating back to a list page)
 * doesn't refetch — reasonable for FitMirror's admin/kiosk data, which
 * isn't updated by other users often enough to need always-fresh reads.
 * Mutations never retry automatically: a failed POST/PATCH/DELETE should
 * surface to the user rather than silently firing twice.
 */
export function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        gcTime: 5 * 60_000,
        retry: 1,
        refetchOnWindowFocus: false,
      },
      mutations: {
        retry: 0,
      },
    },
  });
}
