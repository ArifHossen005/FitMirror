/**
 * Shared MediaPipe pose/face detection and garment-warping render engine,
 * consumed by apps/kiosk and apps/portal. Built out in Phase 6 — see
 * PROGRESS.md "6.B Kiosk App (React + WebRTC + MediaPipe)". This package
 * exists now (Phase 1.B) purely so both apps can declare the workspace
 * dependency ahead of time; there is no try-on logic to implement until
 * the backend's garment_assets/anchor_points schema (Phase 6.A) lands.
 */
export const TRYON_PACKAGE_VERSION = '0.0.0';
