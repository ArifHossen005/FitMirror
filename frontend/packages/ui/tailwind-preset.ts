import type { Config } from 'tailwindcss';

import { colors, fontFamily, fontSize, fontWeight, radii, shadows, spacing } from './src/tokens';

/**
 * Shared Tailwind preset — every app's tailwind.config.ts extends this
 * instead of redefining the design tokens, so a token change (e.g. the
 * brand color scale) takes effect across dashboard/kiosk/portal/mission-
 * control from a single edit. App-specific `content` globs and any
 * app-only theme extensions stay in each app's own config.
 */
const preset: Omit<Config, 'content'> = {
  darkMode: 'class',
  theme: {
    extend: {
      colors,
      fontFamily,
      fontSize,
      fontWeight,
      spacing,
      borderRadius: radii,
      boxShadow: shadows,
    },
  },
  plugins: [],
};

export default preset;
