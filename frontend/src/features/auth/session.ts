/** Shared auth storage + session-expiry redirect (used by API client + AuthProvider). */

export const AUTH_STORAGE_KEY = 'mitra_auth'
export const AUTH_EXPIRED_EVENT = 'mitra:auth-expired'

export function portalFromPath(pathname: string): 'admin' | 'member' | 'super-admin' {
  if (pathname.startsWith('/member')) return 'member'
  if (pathname.startsWith('/super-admin')) return 'super-admin'
  return 'admin'
}

export function loginPathFor(pathname: string): string {
  return `/login/${portalFromPath(pathname)}`
}

/** Decode JWT exp without a library. Returns true if missing/invalid/expired. */
export function isAccessTokenExpired(token: string | null | undefined): boolean {
  if (!token) return true
  try {
    const part = token.split('.')[1]
    if (!part) return true
    const json = atob(part.replace(/-/g, '+').replace(/_/g, '/'))
    const payload = JSON.parse(json) as { exp?: number }
    if (typeof payload.exp !== 'number') return false
    // small skew so we refresh slightly before hard expiry
    return payload.exp * 1000 <= Date.now() + 5_000
  } catch {
    return true
  }
}

/**
 * Clear stored session and send the browser to the correct login page.
 * Uses a full navigation so React Query / shells cannot keep showing protected UI.
 */
export function expireSessionAndRedirectToLogin(options?: {
  returnPath?: string
  reason?: string
}) {
  if (typeof window === 'undefined') return

  localStorage.removeItem(AUTH_STORAGE_KEY)
  window.dispatchEvent(new CustomEvent(AUTH_EXPIRED_EVENT, { detail: options?.reason }))

  const path = options?.returnPath ?? `${window.location.pathname}${window.location.search}`
  if (path.startsWith('/login')) return

  const login = loginPathFor(path)
  const returnUrl = encodeURIComponent(path)
  const target = `${login}?returnUrl=${returnUrl}&expired=1`
  if (window.location.pathname.startsWith('/login')) return
  window.location.assign(target)
}
