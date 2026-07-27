import { Platform } from 'react-native'

export const BRAND = 'Mitra Niidhi Samooh'

export const COLORS = {
  navy: '#0B1F33',
  navyDeep: '#071525',
  teal: '#0F766E',
  tealMid: '#14B8A6',
  tealSoft: '#CCFBF1',
  mint: '#F0FDFA',
  sand: '#F4F7F5',
  bg: '#EEF3F1',
  text: '#0F172A',
  muted: '#5B6B7A',
  danger: '#B91C1C',
  warning: '#B45309',
  success: '#047857',
  white: '#FFFFFF',
  border: '#D7E2DD',
  tabInactive: '#7A8B99',
}

/**
 * Fraunces (display) + DM Sans (UI).
 * Native: loaded from assets/fonts via expo-font.
 * Web: Google Fonts CSS (+ local assets as backup).
 */
export const FONTS = {
  display: Platform.select({
    web: 'Fraunces, Fraunces_700Bold, Georgia, serif',
    default: 'Fraunces_700Bold',
  }) as string,
  displaySoft: Platform.select({
    web: 'Fraunces, Fraunces_600SemiBold, Georgia, serif',
    default: 'Fraunces_600SemiBold',
  }) as string,
  body: Platform.select({
    web: '"DM Sans", DMSans_400Regular, system-ui, sans-serif',
    default: 'DMSans_400Regular',
  }) as string,
  bodyMed: Platform.select({
    web: '"DM Sans", DMSans_500Medium, system-ui, sans-serif',
    default: 'DMSans_500Medium',
  }) as string,
  bodyBold: Platform.select({
    web: '"DM Sans", DMSans_700Bold, system-ui, sans-serif',
    default: 'DMSans_700Bold',
  }) as string,
}

export const SPACE = {
  xs: 6,
  sm: 10,
  md: 16,
  lg: 22,
  xl: 28,
}

export const RADIUS = {
  sm: 12,
  md: 16,
  lg: 22,
  xl: 28,
}
