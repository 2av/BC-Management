import { useAuth } from '@/features/auth/AuthContext'

const API_BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:5027'

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
    response = await fetch(url, {
      ...options,
      headers,
    })
  } catch (err) {
    const detail = err instanceof Error ? err.message : String(err)
    throw new Error(
      `Cannot reach API (${API_BASE}). ${detail}. Check API is running, HTTPS certificate, and CORS for this site.`,
    )
  }

  if (!response.ok) {
    const body = await response.json().catch(() => ({} as Record<string, unknown>))
    const apiMessage =
      (body as { message?: string }).message ||
      (body as { title?: string }).title ||
      (body as { detail?: string }).detail ||
      (body as { error?: string }).error
    const exception =
      (body as { exception?: string }).exception ||
      (body as { Exception?: string }).Exception
    if (apiMessage && exception) throw new Error(`${apiMessage}\n${exception}`)
    if (apiMessage) throw new Error(apiMessage)
    if (response.status === 403) throw new Error('Access denied (403). You may need to log out and sign in again.')
    if (response.status === 401) throw new Error('Invalid username or password (401).')
    throw new Error(`Request failed (${response.status}) on ${url}`)
  }

  if (response.status === 204) return undefined as T
  return response.json() as Promise<T>
}

export function useApi() {
  const { user } = useAuth()
  return {
    get: <T,>(path: string) => apiFetch<T>(path, {}, user?.accessToken),
    post: <T,>(path: string, body?: unknown) =>
      apiFetch<T>(path, { method: 'POST', body: body ? JSON.stringify(body) : undefined }, user?.accessToken),
    put: <T,>(path: string, body?: unknown) =>
      apiFetch<T>(path, { method: 'PUT', body: body ? JSON.stringify(body) : undefined }, user?.accessToken),
    patch: <T,>(path: string, body?: unknown) =>
      apiFetch<T>(path, { method: 'PATCH', body: body ? JSON.stringify(body) : undefined }, user?.accessToken),
    delete: <T,>(path: string) => apiFetch<T>(path, { method: 'DELETE' }, user?.accessToken),
  }
}
