import { NavLink, Route, Routes } from 'react-router-dom'
import { Bell, FileBarChart2, Gavel, KeyRound, LayoutDashboard, LogOut, Settings, UserRound, Users, Wallet, QrCode } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/features/auth/AuthContext'
import { AppSidebar } from '@/components/layout/AppSidebar'
import { LanguageSwitcher } from '@/components/layout/LanguageSwitcher'
import { Button } from '@/components/ui/button'
import { AdminDashboardPage } from './AdminDashboardPage'
import { AdminGroupsPage } from './AdminGroupsPage'
import { AdminGroupLedgerPage } from './AdminGroupLedgerPage'
import { AdminBiddingPage } from './AdminBiddingPage'
import { AdminPaymentsPage } from './AdminPaymentsPage'
import { AdminMembersPage } from './AdminMembersPage'
import { AdminRandomPicksPage } from './AdminRandomPicksPage'
import { AdminReportsPage } from './AdminReportsPage'
import { AdminSettingsPage } from './AdminSettingsPage'
import { AdminPaymentConfigPage } from './AdminPaymentConfigPage'
import { AdminNotificationsPage } from './AdminNotificationsPage'
import { ChangePasswordPage } from '../shared/ChangePasswordPage'
import { MemberInvoicePage } from '../member/MemberInvoicePage'

export function AdminShell() {
  const { logout, user } = useAuth()
  const { t } = useTranslation()
  return (
    <div className="flex h-screen overflow-hidden bg-background">
      <AppSidebar
        brand="Mitra Niidhi"
        subtitle="Admin workspace"
        items={[
          { to: '/admin', label: t('nav.dashboard'), icon: <LayoutDashboard className="h-4 w-4" />, end: true, isActive: (p) => p === '/admin' || p === '/admin/' },
          { to: '/admin/groups', label: t('nav.groups'), icon: <Users className="h-4 w-4" />, isActive: (p) => p.startsWith('/admin/groups') && !p.includes('/bidding') && !p.includes('/random-picks') },
          { to: '/admin/members', label: t('nav.members'), icon: <UserRound className="h-4 w-4" />, isActive: (p) => p.startsWith('/admin/members') },
          { to: '/admin/groups', label: t('nav.bidding'), icon: <Gavel className="h-4 w-4" />, isActive: (p) => p.includes('/bidding') },
          { to: '/admin/payments', label: t('nav.payments'), icon: <Wallet className="h-4 w-4" />, isActive: (p) => p.startsWith('/admin/payments') },
          { to: '/admin/reports', label: t('nav.reports'), icon: <FileBarChart2 className="h-4 w-4" />, isActive: (p) => p.startsWith('/admin/reports') },
          { to: '/admin/notifications', label: t('nav.notifications'), icon: <Bell className="h-4 w-4" />, isActive: (p) => p.startsWith('/admin/notifications') },
          { to: '/admin/settings', label: t('nav.settings'), icon: <Settings className="h-4 w-4" />, isActive: (p) => p.startsWith('/admin/settings') },
          { to: '/admin/payment-config', label: t('nav.paymentConfig'), icon: <QrCode className="h-4 w-4" />, isActive: (p) => p.startsWith('/admin/payment-config') },
          { to: '/admin/account', label: t('nav.account'), icon: <KeyRound className="h-4 w-4" />, isActive: (p) => p.startsWith('/admin/account') },
        ]}
        footer={
          <div className="space-y-3">
            <div>
              <p className="text-sm font-medium text-white">{user?.fullName}</p>
              <p className="text-xs text-white/55">@{user?.username}</p>
            </div>
            <Button variant="secondary" className="w-full justify-start gap-2" onClick={logout}>
              <LogOut className="h-4 w-4" />
              {t('nav.signOut')}
            </Button>
          </div>
        }
      />
      <main className="min-h-0 flex-1 overflow-y-auto">
        <header className="sticky top-0 z-10 border-b border-border bg-card/90 px-6 py-3 backdrop-blur">
          <div className="flex items-center justify-between">
            <p className="text-sm text-muted-foreground">Organisation console</p>
            <div className="flex items-center gap-3">
              <LanguageSwitcher />
              <NavLink to="/" className="text-sm font-medium text-primary hover:underline">{t('nav.switchPortal')}</NavLink>
            </div>
          </div>
        </header>
        <div className="p-6">
          <Routes>
            <Route index element={<AdminDashboardPage />} />
            <Route path="groups" element={<AdminGroupsPage />} />
            <Route path="groups/:id" element={<AdminGroupLedgerPage />} />
            <Route path="groups/:groupId/bidding" element={<AdminBiddingPage />} />
            <Route path="groups/:groupId/random-picks" element={<AdminRandomPicksPage />} />
            <Route path="groups/:groupId/members/:memberId/invoice" element={<MemberInvoicePage />} />
            <Route path="members" element={<AdminMembersPage />} />
            <Route path="payments" element={<AdminPaymentsPage />} />
            <Route path="reports" element={<AdminReportsPage />} />
            <Route path="notifications" element={<AdminNotificationsPage />} />
            <Route path="settings" element={<AdminSettingsPage />} />
            <Route path="payment-config" element={<AdminPaymentConfigPage />} />
            <Route path="account" element={<ChangePasswordPage />} />
          </Routes>
        </div>
      </main>
    </div>
  )
}
