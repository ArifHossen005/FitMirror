import { ErrorState } from '@fitmirror/ui';

export interface ErrorFallbackProps {
  resetError: () => void;
}

/**
 * Kiosk runs unattended in-store — this fallback must stay legible on a
 * bright shop-floor display and offer a one-tap recovery, since there is
 * no staff member watching a console for errors.
 */
export function ErrorFallback({ resetError }: ErrorFallbackProps) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-neutral-950 text-white">
      <ErrorState
        title="The try-on screen hit an unexpected error"
        description="Tap to restart the try-on experience."
        retryLabel="Restart"
        onRetry={resetError}
        className="text-white [&_p]:text-neutral-300"
      />
    </div>
  );
}
