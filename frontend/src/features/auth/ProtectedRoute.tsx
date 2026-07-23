import { Navigate, useLocation } from 'react-router-dom'
import { useAuth, type UserRole } from './AuthContext'
import type { ReactNode } from 'react'

export function ProtectedRoute({
  children,
  roles,
}: {
  children: ReactNode
  roles: UserRole[]
}) {
  const { user } = useAuth()
  const location = useLocation()

  if (!user) {
    return <Navigate to="/" replace state={{ from: location }} />
  }

  if (!roles.includes(user.role)) {
    return <Navigate to="/" replace />
  }

  return children
}
