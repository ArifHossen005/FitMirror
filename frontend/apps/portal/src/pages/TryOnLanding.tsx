import { useParams } from 'react-router-dom';

/**
 * Landing page for a kiosk-generated QR handoff link
 * (`{PORTAL_URL}/try-on/:token`, see PROGRESS.md Phase 6.A "QR try-on token
 * API"). Token resolution against the backend and the actual camera view
 * land in Phase 6.C — this route exists now so the URL shape is fixed
 * before the kiosk starts generating links against it.
 */
export function TryOnLanding() {
  const { token } = useParams<{ token: string }>();

  return (
    <div className="flex min-h-screen flex-col items-center justify-center p-6 text-center">
      <h1 className="text-2xl font-semibold text-neutral-900">Continue your try-on</h1>
      <p className="mt-2 text-neutral-500">Token: {token}</p>
    </div>
  );
}
