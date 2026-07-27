import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { Platform } from 'react-native'
import * as SecureStore from 'expo-secure-store'
import { apiFetch } from '../api'
import type { AuthUser } from '../types'
import {
  registerForPushNotificationsAsync,
  unregisterPushToken,
} from '../notifications/push'

const STORAGE_KEY = 'mitra_member_auth'
const PUSH_TOKEN_KEY = 'mitra_member_push_token'

type AuthContextValue = {
  user: AuthUser | null
  ready: boolean
  login: (username: string, password: string, portal?: 'Member' | 'ClientAdmin' | 'SuperAdmin') => Promise<void>
  logout: () => Promise<void>
  updateUser: (patch: Partial<AuthUser>) => Promise<void>
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined)

async function storageGet(): Promise<string | null> {
  if (Platform.OS === 'web') {
    try {
      return localStorage.getItem(STORAGE_KEY)
    } catch {
      return null
    }
  }
  return SecureStore.getItemAsync(STORAGE_KEY)
}

async function storageSet(value: string): Promise<void> {
  if (Platform.OS === 'web') {
    localStorage.setItem(STORAGE_KEY, value)
    return
  }
  await SecureStore.setItemAsync(STORAGE_KEY, value)
}

async function storageDelete(): Promise<void> {
  if (Platform.OS === 'web') {
    localStorage.removeItem(STORAGE_KEY)
    localStorage.removeItem(PUSH_TOKEN_KEY)
    return
  }
  await SecureStore.deleteItemAsync(STORAGE_KEY)
}

async function pushTokenGet(): Promise<string | null> {
  if (Platform.OS === 'web') {
    try {
      return localStorage.getItem(PUSH_TOKEN_KEY)
    } catch {
      return null
    }
  }
  return SecureStore.getItemAsync(PUSH_TOKEN_KEY)
}

async function pushTokenSet(value: string): Promise<void> {
  if (Platform.OS === 'web') {
    localStorage.setItem(PUSH_TOKEN_KEY, value)
    return
  }
  await SecureStore.setItemAsync(PUSH_TOKEN_KEY, value)
}

async function pushTokenDelete(): Promise<void> {
  if (Platform.OS === 'web') {
    localStorage.removeItem(PUSH_TOKEN_KEY)
    return
  }
  try {
    await SecureStore.deleteItemAsync(PUSH_TOKEN_KEY)
  } catch {
    // ignore
  }
}

async function syncPushRegistration(accessToken: string): Promise<void> {
  try {
    const token = await registerForPushNotificationsAsync(accessToken)
    if (token) await pushTokenSet(token)
  } catch (err) {
    console.warn('Push registration failed', err)
  }
}

async function readStored(): Promise<AuthUser | null> {
  try {
    const raw = await storageGet()
    if (!raw) return null
    return JSON.parse(raw) as AuthUser
  } catch {
    return null
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [ready, setReady] = useState(false)

  useEffect(() => {
    let alive = true
    ;(async () => {
      const stored = await readStored()
      if (alive) {
        setUser(stored)
        setReady(true)
      }
      if (stored?.accessToken) {
        void syncPushRegistration(stored.accessToken)
      }
    })()
    return () => {
      alive = false
    }
  }, [])

  const login = useCallback(async (username: string, password: string, portal: 'Member' | 'ClientAdmin' | 'SuperAdmin' = 'Member') => {
    const next = await apiFetch<AuthUser>('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify({
        username: username.trim(),
        password,
        portal,
      }),
    })
    if (portal === 'Member' && next.role !== 'Member') {
      throw new Error('Member login failed.')
    }
    if (portal === 'ClientAdmin' && next.role !== 'ClientAdmin') {
      throw new Error('Admin login failed.')
    }
    if (portal === 'SuperAdmin' && next.role !== 'SuperAdmin') {
      throw new Error('Super admin login failed.')
    }
    await storageSet(JSON.stringify(next))
    setUser(next)
    if (next.role === 'Member') {
      void syncPushRegistration(next.accessToken)
    }
  }, [])

  const logout = useCallback(async () => {
    const token = user?.accessToken
    const pushToken = await pushTokenGet()
    if (token && pushToken) {
      await unregisterPushToken(token, pushToken)
    }
    await pushTokenDelete()
    await storageDelete()
    setUser(null)
  }, [user?.accessToken])

  const updateUser = useCallback(async (patch: Partial<AuthUser>) => {
    setUser((prev) => {
      if (!prev) return prev
      const next = { ...prev, ...patch }
      void storageSet(JSON.stringify(next))
      return next
    })
  }, [])

  const value = useMemo(
    () => ({ user, ready, login, logout, updateUser }),
    [user, ready, login, logout, updateUser],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
