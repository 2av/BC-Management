import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import {
  AUTH_EXPIRED_EVENT,
  AUTH_STORAGE_KEY,
  expireSessionAndRedirectToLogin,
  isAccessTokenExpired,
} from './session'

export type UserRole = 'SuperAdmin' | 'ClientAdmin' | 'Member'

export type AuthUser = {
  id: number
  username: string
  fullName: string
  role: UserRole
  clientId: number | null
  accessToken: string
  mustChangePassword?: boolean
}

type AuthContextValue = {
  user: AuthUser | null
  login: (user: AuthUser) => void
  logout: () => void
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined)

function readStoredUser(): AuthUser | null {
  try {
    const raw = localStorage.getItem(AUTH_STORAGE_KEY)
    if (!raw) return null
    const user = JSON.parse(raw) as AuthUser
    if (isAccessTokenExpired(user.accessToken)) {
      localStorage.removeItem(AUTH_STORAGE_KEY)
      return null
    }
    return user
  } catch {
    return null
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(() => readStoredUser())

  useEffect(() => {
    const onExpired = () => setUser(null)
    window.addEventListener(AUTH_EXPIRED_EVENT, onExpired)
    return () => window.removeEventListener(AUTH_EXPIRED_EVENT, onExpired)
  }, [])

  // If the tab stays open past JWT expiry, clear and go to login.
  useEffect(() => {
    if (!user?.accessToken) return
    const check = () => {
      if (isAccessTokenExpired(user.accessToken)) {
        expireSessionAndRedirectToLogin({ reason: 'token_expired' })
      }
    }
    check()
    const id = window.setInterval(check, 30_000)
    return () => window.clearInterval(id)
  }, [user?.accessToken])

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      login: (next) => {
        localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(next))
        setUser(next)
      },
      logout: () => {
        localStorage.removeItem(AUTH_STORAGE_KEY)
        setUser(null)
      },
    }),
    [user],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
