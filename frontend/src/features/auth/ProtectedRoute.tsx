import { Navigate, useLocation } from 'react-router-dom'
import { useAuth, type UserRole } from './AuthContext'
import { expireSessionAndRedirectToLogin, isAccessTokenExpired, loginPathFor } from './session'
import type { ReactNode } from 'react'

export function ProtectedRoute({
  children,
  roles,
}: {
  children: ReactNode
  roles: UserRole[]
}) {
  const { user, logout } = useAuth()
  const location = useLocation()

  if (user && isAccessTokenExpired(user.accessToken)) {
    logout()
    expireSessionAndRedirectToLogin({
      returnPath: `${location.pathname}${location.search}`,
      reason: 'token_expired',
    })
    return null
  }

  if (!user) {
    return (
      <Navigate
        to={loginPathFor(location.pathname)}
        replace
        state={{ from: location }}
      />
    )
  }

  if (!roles.includes(user.role)) {
    return <Navigate to="/" replace />
  }

  return children
}
