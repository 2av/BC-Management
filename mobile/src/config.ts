import { BRAND, COLORS } from './theme'

export { BRAND, COLORS }

import Constants from 'expo-constants'

/** Local API for laptop/web; set EXPO_PUBLIC_API_URL to override (e.g. live or LAN IP). */
const fromEnv = (process.env.EXPO_PUBLIC_API_URL as string | undefined)?.replace(/\/$/, '')
const fromExtra = (Constants.expoConfig?.extra?.apiUrl as string | undefined)?.replace(/\/$/, '')

export const API_BASE = fromEnv || fromExtra || 'http://localhost:5027'
