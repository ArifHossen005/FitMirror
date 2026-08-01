/**
 * Extends (never replaces) Tailwind's default spacing scale — see
 * tailwind-preset.cjs where this is spread into `theme.extend.spacing`.
 * Named tokens beyond the numeric scale exist for values that recur across
 * apps with a specific UI meaning, so a spacing change has one place to
 * happen instead of a find-and-replace across four apps.
 */
export const spacing = {
  'shell-topbar': '4rem',
  'shell-sidebar': '16rem',
  'shell-sidebar-collapsed': '4.5rem',
  'kiosk-safe': '2rem', // edge padding on kiosk full-screen layouts
} as const;

export const radii = {
  none: '0px',
  sm: '0.25rem',
  DEFAULT: '0.5rem',
  md: '0.625rem',
  lg: '0.75rem',
  xl: '1rem',
  '2xl': '1.5rem',
  full: '9999px',
} as const;

export const shadows = {
  sm: '0 1px 2px 0 rgb(15 23 42 / 0.05)',
  DEFAULT: '0 1px 3px 0 rgb(15 23 42 / 0.1), 0 1px 2px -1px rgb(15 23 42 / 0.1)',
  md: '0 4px 6px -1px rgb(15 23 42 / 0.1), 0 2px 4px -2px rgb(15 23 42 / 0.1)',
  lg: '0 10px 15px -3px rgb(15 23 42 / 0.1), 0 4px 6px -4px rgb(15 23 42 / 0.1)',
  xl: '0 20px 25px -5px rgb(15 23 42 / 0.1), 0 8px 10px -6px rgb(15 23 42 / 0.1)',
  // Elevated kiosk cards sit on a bright, often outdoor-lit screen — a
  // slightly heavier shadow keeps them legible against busy backgrounds.
  kiosk: '0 12px 32px -8px rgb(15 23 42 / 0.25)',
} as const;
