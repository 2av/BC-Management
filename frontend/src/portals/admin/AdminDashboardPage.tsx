import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { CreditCard, Layers, Users, Wallet } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { formatInr, type DashboardStats } from '@/features/groups/types'
import { PageHeader } from '@/components/layout/PageHeader'
import { StatCard } from '@/components/layout/StatCard'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useAuth } from '@/features/auth/AuthContext'

export function AdminDashboardPage() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const api = useApi()
  const qc = useQueryClient()
  const [message, setMessage] = useState<string | null>(null)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)

  const { data, isLoading, error } = useQuery({
    queryKey: ['admin-dashboard'],
    queryFn: () => api.get<DashboardStats>('/api/dashboard/admin'),
  })

  const markPaid = useMutation({
    mutationFn: (id: number) => api.patch(`/api/payments/${id}`, { paymentStatus: 'paid' }),
    onSuccess: async () => {
      setMessage('Marked as paid.')
      setErrorMsg(null)
      await qc.invalidateQueries({ queryKey: ['admin-dashboard'] })
      await qc.invalidateQueries({ queryKey: ['group-payments'] })
      await qc.invalidateQueries({ queryKey: ['groups'] })
    },
    onError: (e: Error) => setErrorMsg(e.message),
  })

  const pendingPayments = data?.recentPayments ?? []
  const withUtr = pendingPayments.filter((p) => p.transactionId).length

  return (
    <div>
      <PageHeader
        title={t('admin.dashboardTitle')}
        description={`${t('common.welcomeBack', { name: user?.fullName })} ${t('admin.dashboardDesc')}`}
        actions={
          <Button asChild>
            <Link to="/admin/groups">{t('admin.viewAllGroups')}</Link>
          </Button>
        }
      />

      {isLoading ? <p className="text-sm text-muted-foreground">{t('common.loading')}</p> : null}
      {error ? <p className="text-sm text-destructive">{(error as Error).message}</p> : null}
      {message ? <p className="mb-3 text-sm text-emerald-700">{message}</p> : null}
      {errorMsg ? <p className="mb-3 text-sm text-destructive">{errorMsg}</p> : null}

      {data ? (
        <>
          <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
              label={t('admin.activeGroups')}
              value={`${data.activeGroups}`}
              hint={t('admin.completedHint', { completed: data.completedGroups, total: data.totalGroups })}
              icon={<Layers className="h-5 w-5" />}
            />
            <StatCard
              label={t('admin.members')}
              value={`${data.totalMembers}`}
              icon={<Users className="h-5 w-5" />}
            />
            <StatCard
              label={t('admin.collected')}
              value={formatInr(data.totalCollected)}
              hint={t('admin.thisMonth', { amount: formatInr(data.thisMonthCollected) })}
              icon={<Wallet className="h-5 w-5" />}
            />
            <StatCard
              label={t('admin.cashInHand')}
              value={formatInr(data.cashInHand)}
              hint={t('admin.distributed', { amount: formatInr(data.totalDistributed) })}
              icon={<CreditCard className="h-5 w-5" />}
            />
          </div>

          <Card className="mb-6">
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 space-y-0">
              <div>
                <CardTitle>{t('admin.pendingPayments')}</CardTitle>
                <CardDescription>
                  {t('admin.pendingPaymentsDesc', { count: pendingPayments.length, utr: withUtr })}
                </CardDescription>
              </div>
              <Button asChild size="sm" variant="outline">
                <Link to="/admin/payments">{t('nav.payments')}</Link>
              </Button>
            </CardHeader>
            <CardContent>
              <div className="overflow-x-auto rounded-lg border border-border">
                <table className="w-full min-w-[880px] text-left text-sm">
                  <thead className="bg-muted/60 text-muted-foreground">
                    <tr>
                      <th className="px-4 py-3 font-medium">{t('common.group')}</th>
                      <th className="px-4 py-3 font-medium">{t('common.member')}</th>
                      <th className="px-4 py-3 font-medium">{t('common.month')}</th>
                      <th className="px-4 py-3 font-medium text-right">{t('common.amount')}</th>
                      <th className="px-4 py-3 font-medium">UTR</th>
                      <th className="px-4 py-3 font-medium">{t('common.status')}</th>
                      <th className="px-4 py-3 font-medium text-right">{t('common.actions')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {pendingPayments.map((p) => (
                      <tr key={p.id} className="border-t border-border hover:bg-muted/40">
                        <td className="px-4 py-3 font-medium text-foreground">{p.groupName}</td>
                        <td className="px-4 py-3">
                          <p className="font-medium">{p.memberName}</p>
                          <p className="text-xs text-muted-foreground">
                            #{p.memberNumber || '—'}
                            {p.handLabel ? ` · ${p.handLabel}` : ''}
                          </p>
                        </td>
                        <td className="px-4 py-3">M{p.monthNumber}</td>
                        <td className="px-4 py-3 text-right tabular-nums">{formatInr(p.paymentAmount)}</td>
                        <td className="px-4 py-3 font-mono text-xs">
                          {p.transactionId ? (
                            <span className="rounded bg-teal-soft px-1.5 py-0.5 text-primary">{p.transactionId}</span>
                          ) : (
                            <span className="text-muted-foreground">—</span>
                          )}
                        </td>
                        <td className="px-4 py-3">
                          <Badge variant={p.transactionId ? 'warning' : 'muted'}>
                            {p.transactionId ? t('admin.awaitingConfirm') : t('common.pending')}
                          </Badge>
                        </td>
                        <td className="px-4 py-3 text-right">
                          <div className="flex justify-end gap-2">
                            <Button asChild size="sm" variant="outline">
                              <Link to={`/admin/payments`}>{t('common.view')}</Link>
                            </Button>
                            <Button
                              size="sm"
                              disabled={markPaid.isPending}
                              onClick={() => markPaid.mutate(p.id)}
                            >
                              {t('admin.markPaid')}
                            </Button>
                          </div>
                        </td>
                      </tr>
                    ))}
                    {pendingPayments.length === 0 ? (
                      <tr>
                        <td colSpan={7} className="px-4 py-10 text-center text-muted-foreground">
                          {t('admin.noPendingPayments')}
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex-row items-center justify-between space-y-0">
              <div>
                <CardTitle>{t('admin.recentGroups')}</CardTitle>
                <CardDescription>{t('admin.recentGroupsDesc')}</CardDescription>
              </div>
            </CardHeader>
            <CardContent>
              <div className="overflow-x-auto rounded-lg border border-border">
                <table className="w-full min-w-[720px] text-left text-sm">
                  <thead className="bg-muted/60 text-muted-foreground">
                    <tr>
                      <th className="px-4 py-3 font-medium">{t('common.group')}</th>
                      <th className="px-4 py-3 font-medium">{t('common.status')}</th>
                      <th className="px-4 py-3 font-medium">{t('admin.members')}</th>
                      <th className="px-4 py-3 font-medium">{t('admin.contribution')}</th>
                      <th className="px-4 py-3 font-medium">{t('admin.progress')}</th>
                      <th className="px-4 py-3 font-medium">{t('admin.pending')}</th>
                      <th className="px-4 py-3 font-medium" />
                    </tr>
                  </thead>
                  <tbody>
                    {data.recentGroups.map((g) => (
                      <tr key={g.id} className="border-t border-border">
                        <td className="px-4 py-3 font-medium text-foreground">{g.groupName}</td>
                        <td className="px-4 py-3">
                          <Badge variant={g.status === 'active' ? 'success' : 'muted'}>{g.status}</Badge>
                        </td>
                        <td className="px-4 py-3">{g.totalMembers}</td>
                        <td className="px-4 py-3">{formatInr(g.monthlyContribution)}</td>
                        <td className="px-4 py-3">
                          {t('admin.monthsProgress', { done: g.completedMonths, total: g.totalMembers })}
                        </td>
                        <td className="px-4 py-3">{formatInr(g.pendingAmount)}</td>
                        <td className="px-4 py-3 text-right">
                          <Button asChild variant="outline" size="sm">
                            <Link to={`/admin/groups/${g.id}`}>{t('common.ledger')}</Link>
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        </>
      ) : null}
    </div>
  )
}
