import { API_BASE } from './config'

export class ApiError extends Error {
  status: number
  constructor(message: string, status: number) {
    super(message)
    this.status = status
  }
}

/** Optional hook — does not clear stored session (logout is manual only). */
let onUnauthorized: (() => void) | null = null

export function setUnauthorizedHandler(handler: (() => void) | null) {
  onUnauthorized = handler
}

export async function apiFetch<T>(
  path: string,
  options: RequestInit = {},
  token?: string | null,
): Promise<T> {
  const headers = new Headers(options.headers)
  if (!(options.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json')
  }
  if (token) headers.set('Authorization', `Bearer ${token}`)

  const url = `${API_BASE}${path}`
  let response: Response
  try {
    response = await fetch(url, { ...options, headers })
  } catch (err) {
    const detail = err instanceof Error ? err.message : String(err)
    throw new ApiError(`Cannot reach API (${API_BASE}). ${detail}`, 0)
  }

  if (!response.ok) {
    // Keep the stored login; member must tap Log out explicitly.
    if (response.status === 401) onUnauthorized?.()
    const body = (await response.json().catch(() => ({}))) as { message?: string }
    throw new ApiError(body.message ?? `Request failed (${response.status})`, response.status)
  }

  if (response.status === 204) return undefined as T
  return response.json() as Promise<T>
}
