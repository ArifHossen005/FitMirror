export function KioskHome() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-neutral-950 p-6 text-center text-white">
      <h1 className="text-kiosk-2xl font-semibold">FitMirror</h1>
      <p className="mt-2 text-neutral-400">
        Kiosk try-on experience — built out in Phase 6 (WebRTC + MediaPipe render pipeline).
      </p>
    </div>
  );
}
