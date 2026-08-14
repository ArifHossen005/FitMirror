/**
 * Brand and semantic color scales, shared by the Tailwind preset
 * (tailwind-preset.cjs) and any component that needs a raw token value
 * outside of a Tailwind class (e.g. an inline SVG fill, a chart series).
 *
 * `brand` is FitMirror's primary scale. Semantic colors (success/warning/
 * danger/info) are separate scales so a future rebrand only touches
 * `brand`, never the meaning-carrying colors.
 */
export const colors = {
  brand: {
    50: '#f2f6ff',
    100: '#e0e9ff',
    200: '#c2d3ff',
    300: '#9bb3ff',
    400: '#6f8bff',
    500: '#4a63f5', // primary
    600: '#3a4bd1',
    700: '#2e3aa8',
    800: '#262f83',
    900: '#212a68',
    950: '#141a42',
  },
  success: {
    50: '#effdf5',
    100: '#d7f9e5',
    500: '#16a34a',
    600: '#15803d',
    700: '#166534',
  },
  warning: {
    50: '#fffbeb',
    100: '#fef3c7',
    500: '#d97706',
    600: '#b45309',
    700: '#92400e',
  },
  danger: {
    50: '#fef2f2',
    100: '#fee2e2',
    500: '#dc2626',
    600: '#b91c1c',
    700: '#991b1b',
  },
  info: {
    50: '#eff6ff',
    100: '#dbeafe',
    500: '#2563eb',
    600: '#1d4ed8',
    700: '#1e40af',
  },
  neutral: {
    0: '#ffffff',
    50: '#f8fafc',
    100: '#f1f5f9',
    200: '#e2e8f0',
    300: '#cbd5e1',
    400: '#94a3b8',
    500: '#64748b',
    600: '#475569',
    700: '#334155',
    800: '#1e293b',
    900: '#0f172a',
    950: '#020617',
  },
} as const;

/** Default chart series palette — brand first, then semantic colors, so a chart with up to 5 series never needs an explicit color prop (see components/Chart.tsx). */
export const chartPalette = [
  colors.brand[500],
  colors.info[500],
  colors.success[500],
  colors.warning[500],
  colors.danger[500],
] as const;
