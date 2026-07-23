import { useQuery } from '@tanstack/react-query'
import { NavLink, Route, Routes } from 'react-router-dom'
import {
  Building2,
  CalendarCheck,
  CreditCard,
  ClipboardList,
  KeyRound,
  LayoutDashboard,
  LogOut,
  Settings,
  Shield,
} from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/features/auth/AuthContext'
import { AppSidebar } from '@/components/layout/AppSidebar'
import { LanguageSwitcher } from '@/components/layout/LanguageSwitcher'
import { PageHeader } from '@/components/layout/PageHeader'
import { StatCard } from '@/components/layout/StatCard'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useApi } from '@/shared/api/client'
import { formatInr } from '@/features/groups/types'
import { SuperAdminClientsPage } from './SuperAdminClientsPage'
import { SuperAdminPlansPage } from './SuperAdminPlansPage'
import { SuperAdminSubscriptionsPage } from './SuperAdminSubscriptionsPage'
import { SuperAdminPaymentsPage } from './SuperAdminPaymentsPage'
import { SuperAdminAuditPage } from './SuperAdminAuditPage'
import { SuperAdminSettingsPage } from './SuperAdminSettingsPage'
import { ChangePasswordPage } from '../shared/ChangePasswordPage'

type Dashboard = {
  clientCount: number
  activeClients: number
  groupCount: number
  memberCount: number
  monthlyRevenue: number
  expiringSoon: number
}

function Dashboard() {
  const { user } = useAuth()
  const { t } = useTranslation()
  const api = useApi()
  const { data } = useQuery({
    queryKey: ['sa-dashboard'],
    queryFn: () => api.get<Dashboard>('/api/super-admin/dashboard'),
  })

  return (
    <div>
      <PageHeader
        title={t('superAdmin.dashboardTitle')}
        description={t('common.welcome', { name: user?.fullName })}
        actions={<Badge>Super Admin</Badge>}
      />
      <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label={t('superAdmin.clients')} value={`${data?.clientCount ?? '—'}`} icon={<Building2 className="h-5 w-5" />} />
        <StatCard label={t('superAdmin.activeClients')} value={`${data?.activeClients ?? '—'}`} icon={<Shield className="h-5 w-5" />} />
        <StatCard label={t('superAdmin.groups')} value={`${data?.groupCount ?? '—'}`} icon={<CalendarCheck className="h-5 w-5" />} />
        <StatCard label={t('superAdmin.monthlyRevenue')} value={data ? formatInr(data.monthlyRevenue) : '—'} icon={<CreditCard className="h-5 w-5" />} />
      </div>
      {data?.expiringSoon ? (
        <p className="text-sm text-amber-700">{data.expiringSoon} client subscription(s) expiring within 30 days.</p>
      ) : null}
    </div>
  )
}

export function SuperAdminShell() {
  const { logout, user } = useAuth()
  const { t } = useTranslation()
  return (
    <div className="flex h-screen overflow-hidden bg-background">
      <AppSidebar
        brand="Mitra Niidhi"
        subtitle="Platform control"
        items={[
          { to: '/super-admin', label: t('nav.dashboard'), icon: <LayoutDashboard className="h-4 w-4" />, end: true, isActive: (p) => p === '/super-admin' || p === '/super-admin/' },
          { to: '/super-admin/clients', label: t('nav.clients'), icon: <Building2 className="h-4 w-4" />, isActive: (p) => p.startsWith('/super-admin/clients') },
          { to: '/super-admin/plans', label: t('nav.plans'), icon: <CalendarCheck className="h-4 w-4" />, isActive: (p) => p.startsWith('/super-admin/plans') },
          { to: '/super-admin/subscriptions', label: t('nav.subscriptions'), icon: <Shield className="h-4 w-4" />, isActive: (p) => p.startsWith('/super-admin/subscriptions') },
          { to: '/super-admin/payments', label: t('nav.payments'), icon: <CreditCard className="h-4 w-4" />, isActive: (p) => p.startsWith('/super-admin/payments') },
          { to: '/super-admin/audit', label: t('nav.auditLog'), icon: <ClipboardList className="h-4 w-4" />, isActive: (p) => p.startsWith('/super-admin/audit') },
          { to: '/super-admin/settings', label: t('nav.platformSettings'), icon: <Settings className="h-4 w-4" />, isActive: (p) => p.startsWith('/super-admin/settings') },
          {
            to: '/super-admin/account',
            label: t('nav.account'),
            icon: <KeyRound className="h-4 w-4" />,
            isActive: (p) => p.startsWith('/super-admin/account'),
          },
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
          <div className="flex items-center justify-between gap-4">
            <p className="text-sm text-muted-foreground">SaaS control plane</p>
            <div className="flex items-center gap-3">
              <LanguageSwitcher />
              <NavLink to="/" className="text-sm font-medium text-primary hover:underline">{t('nav.switchPortal')}</NavLink>
            </div>
          </div>
        </header>
        <div className="p-6">
          <Routes>
            <Route index element={<Dashboard />} />
            <Route path="clients" element={<SuperAdminClientsPage />} />
            <Route path="plans" element={<SuperAdminPlansPage />} />
            <Route path="subscriptions" element={<SuperAdminSubscriptionsPage />} />
            <Route path="payments" element={<SuperAdminPaymentsPage />} />
            <Route path="audit" element={<SuperAdminAuditPage />} />
            <Route path="settings" element={<SuperAdminSettingsPage />} />
            <Route path="account" element={<ChangePasswordPage />} />
          </Routes>
        </div>
      </main>
    </div>
  )
}
