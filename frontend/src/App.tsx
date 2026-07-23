import { Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './features/auth/AuthContext'
import { LandingPage } from './portals/LandingPage'
import { LoginPage } from './features/auth/LoginPage'
import { AdminShell } from './portals/admin/AdminShell'
import { MemberShell } from './portals/member/MemberShell'
import { SuperAdminShell } from './portals/super-admin/SuperAdminShell'
import { ProtectedRoute } from './features/auth/ProtectedRoute'

export default function App() {
  return (
    <AuthProvider>
      <Routes>
        <Route path="/" element={<LandingPage />} />
        <Route path="/login/:portal" element={<LoginPage />} />
        <Route
          path="/admin/*"
          element={
            <ProtectedRoute roles={['ClientAdmin']}>
              <AdminShell />
            </ProtectedRoute>
          }
        />
        <Route
          path="/member/*"
          element={
            <ProtectedRoute roles={['Member']}>
              <MemberShell />
            </ProtectedRoute>
          }
        />
        <Route
          path="/super-admin/*"
          element={
            <ProtectedRoute roles={['SuperAdmin']}>
              <SuperAdminShell />
            </ProtectedRoute>
          }
        />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </AuthProvider>
  )
}
