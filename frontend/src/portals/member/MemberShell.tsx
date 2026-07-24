import { useQuery } from '@tanstack/react-query'
import { NavLink, Route, Routes, Link, useLocation } from 'react-router-dom'
import { Gavel, KeyRound, LayoutDashboard, LogOut, Menu, Bell, Receipt, UserRound, Users, Wallet, X } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/features/auth/AuthContext'
import { useApi } from '@/shared/api/client'
import { formatInr, type MemberDashboard } from '@/features/groups/types'
import { PageHeader } from '@/components/layout/PageHeader'
import { StatCard } from '@/components/layout/StatCard'
import { LanguageSwitcher } from '@/components/layout/LanguageSwitcher'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { PAYMENT_BRAND, UpiQrCard, type PaymentMethods } from '@/components/payments/UpiQrCard'
import { PaymentQrModal } from '@/components/payments/PaymentQrModal'
import { cn } from '@/lib/utils'
import { AdminGroupLedgerPage } from '../admin/AdminGroupLedgerPage'
import { MemberBiddingPage } from './MemberBiddingPage'
import { MemberPaymentsPage, MemberPayRedirect } from './MemberPaymentsPage'
import { MemberRandomPicksPage } from './MemberRandomPicksPage'
import { MemberProfilePage } from './MemberProfilePage'
import { MemberInvoicePage } from './MemberInvoicePage'
import { ChangePasswordPage } from '../shared/ChangePasswordPage'
import { NotificationsInboxPage } from '../shared/NotificationsInboxPage'

function formatMonthYear(iso: string) {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso)
  if (m) {
    const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]))
    return d.toLocaleDateString('en-IN', { month: 'short', year: 'numeric' })
  }
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleDateString('en-IN', { month: 'short', year: 'numeric' })
}

const memberNav = (t: (k: string) => string) => [
  {
    to: '/member',
    label: t('nav.dashboard'),
    icon: LayoutDashboard,
    isActive: (p: string) => p === '/member' || p === '/member/',
  },
  {
    to: '/member/bidding',
    label: t('nav.bidding'),
    icon: Gavel,
    isActive: (p: string) => p.startsWith('/member/bidding'),
  },
  {
    to: '/member/payments',
    label: t('nav.payments'),
    icon: Wallet,
    isActive: (p: string) => p.startsWith('/member/payments') || p.startsWith('/member/pay'),
  },
  {
    to: '/member/notifications',
    label: t('nav.notifications'),
    icon: Bell,
    isActive: (p: string) => p.startsWith('/member/notifications'),
  },
  {
    to: '/member/profile',
    label: t('nav.profile'),
    icon: UserRound,
    isActive: (p: string) => p.startsWith('/member/profile'),
  },
  {
    to: '/member/account',
    label: t('nav.account'),
    icon: KeyRound,
    isActive: (p: string) => p.startsWith('/member/account'),
  },
]

function MemberNavLinks({ onNavigate, className }: { onNavigate?: () => void; className?: string }) {
  const { pathname } = useLocation()
  const { t } = useTranslation()
  return (
    <nav className={cn('flex items-center gap-1', className)}>
      {memberNav(t).map((item) => {
        const Icon = item.icon
        const active = item.isActive(pathname)
        return (
          <NavLink
            key={item.to}
            to={item.to}
            onClick={onNavigate}
            aria-current={active ? 'page' : undefined}
            className={cn(
              'inline-flex h-9 shrink-0 items-center gap-2 whitespace-nowrap rounded-lg px-3 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground',
              active && 'bg-teal-soft text-primary',
            )}
          >
            <Icon className="h-4 w-4 shrink-0" />
            <span>{item.label}</span>
          </NavLink>
        )
      })}
    </nav>
  )
}

function MemberDashboardPage() {
  const { t } = useTranslation()
  const api = useApi()
  const [groupTab, setGroupTab] = useState<'active' | 'completed'>('active')
  const [payGroup, setPayGroup] = useState<{
    groupId: number
    groupName: string
    groupMemberId: number
  } | null>(null)

  const { data, isLoading, error } = useQuery({
    queryKey: ['member-dashboard'],
    queryFn: () => api.get<MemberDashboard>('/api/members/me/dashboard'),
  })

  const { data: methods } = useQuery({
    queryKey: ['payment-methods'],
    queryFn: () => api.get<PaymentMethods>('/api/members/me/payment-methods'),
  })

  const activeGroups = data?.groups.filter((g) => g.status === 'active') ?? []
  const completedGroups = data?.groups.filter((g) => g.status !== 'active') ?? []
  const visibleGroups = groupTab === 'active' ? activeGroups : completedGroups

  return (
    <div>
      <PageHeader
        title={t('member.dashboardTitle')}
        description={
          data
            ? `${t('common.welcome', { name: data.fullName })} ${t('member.dashboardDesc')}`
            : t('common.loading')
        }
        actions={<Badge variant="success">Member</Badge>}
      />

      {isLoading ? <p className="text-sm text-muted-foreground">{t('common.loading')}</p> : null}
      {error ? <p className="text-sm text-destructive">{(error as Error).message}</p> : null}

      {data ? (
        <>
          <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label={t('member.groupsJoined')} value={`${data.groupCount}`} icon={<Users className="h-5 w-5" />} />
            <StatCard label={t('member.totalPaid')} value={formatInr(data.totalPaid)} icon={<Wallet className="h-5 w-5" />} />
            <StatCard label={t('member.amountReceived')} value={formatInr(data.totalReceived)} icon={<Receipt className="h-5 w-5" />} />
            <StatCard label={t('member.pendingDues')} value={formatInr(data.pendingDues)} icon={<Gavel className="h-5 w-5" />} />
          </div>

          <div className="mb-2">
            <UpiQrCard
              methods={
                methods
                  ? {
                      ...methods,
                      payeeName: PAYMENT_BRAND,
                      paymentNote: PAYMENT_BRAND,
                    }
                  : methods
              }
              title={PAYMENT_BRAND}
              paymentText={PAYMENT_BRAND}
            />
            {methods?.qrEnabled ? (
              <p className="-mt-4 mb-6 text-sm text-muted-foreground">{t('member.dashboardPayHint')}</p>
            ) : null}
          </div>

          <Card>
            <CardHeader>
              <CardTitle>{t('member.yourGroups')}</CardTitle>
              <CardDescription>{t('member.yourGroupsDesc')}</CardDescription>
              <div className="mt-4 flex gap-2">
                <Button
                  size="sm"
                  variant={groupTab === 'active' ? 'default' : 'outline'}
                  onClick={() => setGroupTab('active')}
                >
                  {t('common.active')} ({activeGroups.length})
                </Button>
                <Button
                  size="sm"
                  variant={groupTab === 'completed' ? 'default' : 'outline'}
                  onClick={() => setGroupTab('completed')}
                >
                  {t('common.completed')} ({completedGroups.length})
                </Button>
              </div>
            </CardHeader>
            <CardContent className="grid gap-3 md:grid-cols-2">
              {visibleGroups.map((g) => {
                const startLabel = formatMonthYear(g.startDate)
                const endLabel = formatMonthYear(g.endDate)
                const progressPct =
                  g.totalMembers > 0 ? Math.min(100, Math.round((g.completedMonths / g.totalMembers) * 100)) : 0
                return (
                  <div key={g.groupMemberId} className="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <div className="mb-3 flex items-start justify-between gap-2">
                      <div>
                        <h3 className="font-semibold">
                          {g.groupName} (Rs. {g.monthlyContribution})
                        </h3>
                        <p className="text-xs text-muted-foreground">
                          {t('common.member')} #{g.memberNumber}
                          {g.handLabel ? ` · ${g.handLabel}` : ''}
                        </p>
                      </div>
                      <Badge variant={g.status === 'active' ? 'success' : 'muted'}>{g.status}</Badge>
                    </div>

                    <div className="mb-3 rounded-lg border border-border bg-muted/40 px-3 py-2 text-xs">
                      <div className="flex flex-wrap items-center justify-between gap-2">
                        <span className="text-muted-foreground">
                          {t('member.startDate')}: <strong className="text-foreground">{startLabel}</strong>
                        </span>
                        <span className="text-muted-foreground">
                          {t('member.endDate')}: <strong className="text-foreground">{endLabel}</strong>
                        </span>
                      </div>
                      <p className="mt-1 text-muted-foreground">
                        {startLabel} – {endLabel}
                      </p>
                    </div>

                    <div className="mb-3">
                      <div className="mb-1 flex items-center justify-between text-xs">
                        <span className="font-medium text-foreground">
                          {t('member.completedMonths')}: {g.completedMonths}/{g.totalMembers}
                        </span>
                        <span className="text-muted-foreground">
                          {t('member.pendingMonths')}: {g.pendingMonths}
                        </span>
                      </div>
                      <div className="h-2 overflow-hidden rounded-full bg-muted">
                        <div className="h-full rounded-full bg-primary transition-all" style={{ width: `${progressPct}%` }} />
                      </div>
                    </div>

                    <div className="mb-4 grid grid-cols-3 gap-2 text-sm">
                      <div>
                        <p className="text-muted-foreground">{t('member.totalPaid')}</p>
                        <p className="font-medium">{formatInr(g.totalPaid)}</p>
                      </div>
                      <div>
                        <p className="text-muted-foreground">{t('member.amountReceived')}</p>
                        <p className="font-medium">{formatInr(g.givenAmount)}</p>
                      </div>
                      <div>
                        <p className="text-muted-foreground">{t('member.profit')}</p>
                        <p className={`font-medium ${g.profit >= 0 ? 'text-emerald-700' : 'text-destructive'}`}>
                          {formatInr(g.profit)}
                        </p>
                      </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <Button asChild variant="outline" className="flex-1">
                        <Link to={`/member/groups/${g.groupId}`}>{t('common.ledger')}</Link>
                      </Button>
                      {g.status === 'active' ? (
                        <>
                          <Button
                            className="flex-1"
                            variant="secondary"
                            onClick={() =>
                              setPayGroup({
                                groupId: g.groupId,
                                groupName: g.groupName,
                                groupMemberId: g.groupMemberId,
                              })
                            }
                          >
                            {t('nav.pay')}
                          </Button>
                          <Button asChild className="flex-1">
                            <Link to="/member/bidding">{t('nav.bidding')}</Link>
                          </Button>
                        </>
                      ) : null}
                    </div>
                  </div>
                )
              })}
              {visibleGroups.length === 0 ? (
                <p className="text-sm text-muted-foreground col-span-full">
                  {groupTab === 'active' ? t('member.noActiveGroups') : t('member.noCompletedGroups')}
                </p>
              ) : null}
            </CardContent>
          </Card>

          <PaymentQrModal
            open={!!payGroup}
            onClose={() => setPayGroup(null)}
            groupId={payGroup?.groupId ?? 0}
            groupName={payGroup?.groupName ?? ''}
            groupMemberId={payGroup?.groupMemberId}
          />
        </>
      ) : null}
    </div>
  )
}

export function MemberShell() {
  const { logout, user } = useAuth()
  const { t } = useTranslation()
  const [mobileOpen, setMobileOpen] = useState(false)

  return (
    <div className="min-h-screen bg-background">
      <header className="sticky top-0 z-20 border-b border-border bg-card/95 backdrop-blur">
        <div className="mx-auto max-w-6xl px-4 sm:px-6">
          <div className="flex h-14 items-center justify-between gap-3">
            <div className="flex min-w-0 items-center gap-3">
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="px-2 lg:hidden"
                aria-label="Toggle menu"
                onClick={() => setMobileOpen((v) => !v)}
              >
                {mobileOpen ? <X className="h-4 w-4" /> : <Menu className="h-4 w-4" />}
              </Button>
              <div className="min-w-0">
                <p className="font-display text-lg leading-none text-navy">Mitra Niidhi</p>
                <p className="mt-1 truncate text-xs text-muted-foreground">Member portal</p>
              </div>
            </div>

            <div className="flex shrink-0 items-center gap-2 sm:gap-3">
              <LanguageSwitcher compact />
              <div className="hidden max-w-[140px] text-right xl:block">
                <p className="truncate text-sm font-medium text-foreground">{user?.fullName}</p>
                <p className="truncate text-xs text-muted-foreground">@{user?.username}</p>
              </div>
              <Button variant="outline" size="sm" onClick={logout} className="shrink-0">
                <LogOut className="h-4 w-4" />
                <span className="hidden sm:inline">{t('nav.signOut')}</span>
              </Button>
            </div>
          </div>

          <div className="hidden border-t border-border/70 py-2 lg:block">
            <MemberNavLinks />
          </div>
        </div>

        {mobileOpen ? (
          <div className="border-t border-border bg-card px-4 py-3 lg:hidden">
            <MemberNavLinks
              className="flex-col items-stretch"
              onNavigate={() => setMobileOpen(false)}
            />
          </div>
        ) : null}
      </header>

      <main className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
        <Routes>
          <Route index element={<MemberDashboardPage />} />
          <Route path="groups/:id" element={<MemberGroupLedgerPage />} />
          <Route path="groups/:groupId/random-picks" element={<MemberRandomPicksPage />} />
          <Route path="bidding" element={<MemberBiddingPage />} />
          <Route path="payments" element={<MemberPaymentsPage />} />
          <Route path="payments/:groupId/:month" element={<MemberPaymentsPage />} />
          <Route path="pay" element={<MemberPayRedirect />} />
          <Route path="pay/:groupId/:month" element={<MemberPayRedirect />} />
          <Route
            path="notifications"
            element={
              <NotificationsInboxPage
                titleKey="member.notificationsTitle"
                descriptionKey="member.notificationsDesc"
              />
            }
          />
          <Route path="profile" element={<MemberProfilePage />} />
          <Route path="groups/:groupId/invoice" element={<MemberInvoicePage />} />
          <Route path="account" element={<ChangePasswordPage />} />
        </Routes>
      </main>
    </div>
  )
}

function MemberGroupLedgerPage() {
  return (
    <div>
      <Button asChild variant="ghost" className="mb-2 px-0">
        <Link to="/member">← Back to dashboard</Link>
      </Button>
      <AdminGroupLedgerPage />
    </div>
  )
}
