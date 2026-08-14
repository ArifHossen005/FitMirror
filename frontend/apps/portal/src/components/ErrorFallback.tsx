import { ErrorState } from '@fitmirror/ui';

export interface ErrorFallbackProps {
  resetError: () => void;
}

/** Rendered by Sentry.ErrorBoundary in main.tsx when a render error escapes the app. */
export function ErrorFallback({ resetError }: ErrorFallbackProps) {
  return (
    <div className="flex min-h-screen items-center justify-center">
      <ErrorState
        title="Something went wrong"
        description="The error has been reported. Try again."
        retryLabel="Try again"
        onRetry={resetError}
      />
    </div>
  );
}
