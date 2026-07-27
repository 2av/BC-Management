import { useState } from 'react'
import { NavLink, Route, Routes } from 'react-router-dom'
import { Bell, FileBarChart2, Gavel, KeyRound, LayoutDashboard, LogOut, Menu, Settings, UserRound, Users, Wallet, QrCode } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/features/auth/AuthContext'
import { AppSidebar } from '@/components/layout/AppSidebar'
import { LanguageSwitcher } from '@/components/layout/LanguageSwitcher'
import { Button } from '@/components/ui/button'
import { AdminDashboardPage } from './AdminDashboardPage'
import { AdminGroupsPage } from './AdminGroupsPage'
import { AdminGroupLedgerPage } from './AdminGroupLedgerPage'
import { AdminBiddingPage } from './AdminBiddingPage'
import { AdminBcChartPage } from './AdminBcChartPage'
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
  const [mobileOpen, setMobileOpen] = useState(false)

  const navItems = [
    { to: '/admin', label: t('nav.dashboard'), icon: <LayoutDashboard className="h-4 w-4" />, end: true, isActive: (p: string) => p === '/admin' || p === '/admin/' },
    { to: '/admin/groups', label: t('nav.groups'), icon: <Users className="h-4 w-4" />, isActive: (p: string) => p.startsWith('/admin/groups') && !p.includes('/bidding') && !p.includes('/random-picks') && !p.includes('/bc-chart') },
    { to: '/admin/members', label: t('nav.members'), icon: <UserRound className="h-4 w-4" />, isActive: (p: string) => p.startsWith('/admin/members') },
    { to: '/admin/groups', label: t('nav.bidding'), icon: <Gavel className="h-4 w-4" />, isActive: (p: string) => p.includes('/bidding') || p.includes('/bc-chart') },
    { to: '/admin/payments', label: t('nav.payments'), icon: <Wallet className="h-4 w-4" />, isActive: (p: string) => p.startsWith('/admin/payments') },
    { to: '/admin/reports', label: t('nav.reports'), icon: <FileBarChart2 className="h-4 w-4" />, isActive: (p: string) => p.startsWith('/admin/reports') },
    { to: '/admin/notifications', label: t('nav.notifications'), icon: <Bell className="h-4 w-4" />, isActive: (p: string) => p.startsWith('/admin/notifications') },
    { to: '/admin/settings', label: t('nav.settings'), icon: <Settings className="h-4 w-4" />, isActive: (p: string) => p.startsWith('/admin/settings') },
    { to: '/admin/payment-config', label: t('nav.paymentConfig'), icon: <QrCode className="h-4 w-4" />, isActive: (p: string) => p.startsWith('/admin/payment-config') },
    { to: '/admin/account', label: t('nav.account'), icon: <KeyRound className="h-4 w-4" />, isActive: (p: string) => p.startsWith('/admin/account') },
  ]

  return (
    <div className="flex h-screen overflow-hidden bg-background">
      <AppSidebar
        brand="Mitra Niidhi"
        subtitle="Admin workspace"
        items={navItems}
        mobileOpen={mobileOpen}
        onNavigate={() => setMobileOpen(false)}
        onClose={() => setMobileOpen(false)}
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
        <header className="sticky top-0 z-10 border-b border-border bg-card/90 px-4 py-3 backdrop-blur sm:px-6">
          <div className="flex items-center justify-between gap-3">
            <div className="flex items-center gap-2">
              <Button
                type="button"
                variant="outline"
                size="icon"
                className="lg:hidden"
                onClick={() => setMobileOpen(true)}
                aria-label="Open menu"
              >
                <Menu className="h-5 w-5" />
              </Button>
              <p className="text-sm text-muted-foreground">Organisation console</p>
            </div>
            <div className="flex items-center gap-3">
              <LanguageSwitcher />
              <NavLink to="/" className="hidden text-sm font-medium text-primary hover:underline sm:inline">
                {t('nav.switchPortal')}
              </NavLink>
            </div>
          </div>
        </header>
        <div className="p-4 sm:p-6">
          <Routes>
            <Route index element={<AdminDashboardPage />} />
            <Route path="groups" element={<AdminGroupsPage />} />
            <Route path="groups/:id" element={<AdminGroupLedgerPage />} />
            <Route path="groups/:groupId/bidding" element={<AdminBiddingPage />} />
            <Route path="groups/:groupId/bc-chart" element={<AdminBcChartPage />} />
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
