import { Button, EmptyState, ErrorState, Skeleton } from '@fitmirror/ui';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { fetchSessions, revokeSession } from '../../lib/auth';

export function SessionsPage() {
  const queryClient = useQueryClient();
  const { data: sessions, isLoading, isError } = useQuery({
    queryKey: ['auth', 'sessions'],
    queryFn: fetchSessions,
  });

  const revokeMutation = useMutation({
    mutationFn: revokeSession,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['auth', 'sessions'] }),
  });

  return (
    <div className="max-w-2xl">
      <h1 className="text-lg font-semibold text-neutral-900">Active sessions</h1>
      <p className="mt-1 text-sm text-neutral-500">
        Devices and browsers currently signed in to your account. Revoke any you don't recognize.
      </p>

      <div className="mt-4 flex flex-col gap-2">
        {isLoading ? (
          <>
            <Skeleton className="h-16 w-full" />
            <Skeleton className="h-16 w-full" />
          </>
        ) : isError ? (
          <ErrorState description="Unable to load your active sessions." />
        ) : sessions && sessions.length > 0 ? (
          sessions.map((session) => (
            <div
              key={session.id}
              className="flex items-center justify-between rounded-md border border-neutral-200 px-4 py-3"
            >
              <div>
                <p className="text-sm font-medium text-neutral-800">
                  {session.name}
                  {session.is_current && (
                    <span className="bg-brand-50 text-brand-700 ml-2 rounded-full px-2 py-0.5 text-xs font-medium">
                      This device
                    </span>
                  )}
                </p>
                <p className="text-xs text-neutral-400">
                  Last used {session.last_used_at ? new Date(session.last_used_at).toLocaleString() : 'never'}
                </p>
              </div>
              {!session.is_current && (
                <Button
                  variant="outline"
                  size="sm"
                  isLoading={revokeMutation.isPending && revokeMutation.variables === session.id}
                  onClick={() => revokeMutation.mutate(session.id)}
                >
                  Revoke
                </Button>
              )}
            </div>
          ))
        ) : (
          <EmptyState title="No active sessions" />
        )}
      </div>
    </div>
  );
}
