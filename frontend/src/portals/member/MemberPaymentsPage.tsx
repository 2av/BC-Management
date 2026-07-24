import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState } from 'react'
import { Link, Navigate, useParams } from 'react-router-dom'
import { ArrowLeft, Search, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { formatInr } from '@/features/groups/types'
import type { MemberPayments } from '@/features/payments/types'
import { PageHeader } from '@/components/layout/PageHeader'
import { StatCard } from '@/components/layout/StatCard'
import { UpiQrCard, PAYMENT_BRAND, type PaymentMethods } from '@/components/payments/UpiQrCard'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { TablePagination } from '@/components/ui/table-pagination'

type Detail = {
  paymentId: number | null
  groupName: string
  monthNumber: number
  amount: number
  memberName: string
  winnerName: string | null
  paymentStatus: string
  transactionId: string | null
  payeeName: string
  paymentNote: string
  qrEnabled: boolean
  upiId: string | null
  qrImageUrl: string | null
  upiUrl: string | null
}

function statusVariant(status: string) {
  if (status === 'paid') return 'success' as const
  if (status === 'pending') return 'warning' as const
  return 'muted' as const
}

export function MemberPaymentsPage() {
  const { t } = useTranslation()
  const api = useApi()
  const { groupId, month } = useParams()
  const gid = Number(groupId)
  const m = Number(month)
  const hasDetail = gid > 0 && m > 0

  const [groupFilter, setGroupFilter] = useState('all')
  const [monthFilter, setMonthFilter] = useState('all')
  const [statusFilter, setStatusFilter] = useState('all')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [utr, setUtr] = useState('')
  const [utrMessage, setUtrMessage] = useState<string | null>(null)
  const [utrError, setUtrError] = useState<string | null>(null)
  const qc = useQueryClient()

  const { data, isLoading, error } = useQuery({
    queryKey: ['my-payments'],
    queryFn: () => api.get<MemberPayments>('/api/members/me/payments'),
  })

  const { data: methods } = useQuery({
    queryKey: ['payment-methods'],
    queryFn: () => api.get<PaymentMethods>('/api/members/me/payment-methods'),
  })

  const { data: detail } = useQuery({
    queryKey: ['payment-detail', gid, m],
    queryFn: () => api.get<Detail>(`/api/members/me/payments/${gid}/${m}`),
    enabled: hasDetail,
  })

  useEffect(() => {
    setUtr(detail?.transactionId ?? '')
    setUtrMessage(null)
    setUtrError(null)
  }, [detail?.transactionId, gid, m])

  const submitUtr = useMutation({
    mutationFn: () =>
      api.post<{ message?: string }>(`/api/members/me/payments/${gid}/${m}/utr`, {
        transactionId: utr.trim(),
      }),
    onSuccess: async (res) => {
      setUtrMessage(res.message ?? t('member.utrSubmitted'))
      setUtrError(null)
      await qc.invalidateQueries({ queryKey: ['payment-detail', gid, m] })
      await qc.invalidateQueries({ queryKey: ['my-payments'] })
    },
    onError: (e: Error) => setUtrError(e.message),
  })

  const groups = useMemo(() => {
    const map = new Map<number, string>()
    data?.payments.forEach((p) => map.set(p.groupId, p.groupName))
    return Array.from(map.entries()).sort((a, b) => a[1].localeCompare(b[1]))
  }, [data])

  const months = useMemo(() => {
    const set = new Set(data?.payments.map((p) => p.monthNumber) ?? [])
    return Array.from(set).sort((a, b) => a - b)
  }, [data])

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase()
    return (data?.payments ?? []).filter((p) => {
      if (groupFilter !== 'all' && p.groupId !== Number(groupFilter)) return false
      if (monthFilter !== 'all' && p.monthNumber !== Number(monthFilter)) return false
      if (statusFilter !== 'all' && p.paymentStatus !== statusFilter) return false
      if (!q) return true
      return (
        p.groupName.toLowerCase().includes(q) ||
        (p.winnerName?.toLowerCase().includes(q) ?? false) ||
        String(p.monthNumber).includes(q)
      )
    })
  }, [data, groupFilter, monthFilter, statusFilter, search])

  const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize))
  const safePage = Math.min(page, totalPages)
  const pageRows = filtered.slice((safePage - 1) * pageSize, safePage * pageSize)

  useEffect(() => {
    setPage(1)
  }, [groupFilter, monthFilter, statusFilter, search, pageSize])

  const hasFilters =
    groupFilter !== 'all' || monthFilter !== 'all' || statusFilter !== 'all' || search.trim() !== ''

  function clearFilters() {
    setGroupFilter('all')
    setMonthFilter('all')
    setStatusFilter('all')
    setSearch('')
  }

  const paymentText = hasDetail && detail?.groupName
    ? `${PAYMENT_BRAND} - ${detail.groupName}`
    : PAYMENT_BRAND

  const detailMethods: PaymentMethods | null =
    hasDetail && detail?.qrImageUrl
      ? {
          qrEnabled: Boolean(detail.qrImageUrl && detail.upiId),
          upiId: detail.upiId ?? '',
          payeeName: detail.payeeName || PAYMENT_BRAND,
          paymentNote: detail.paymentNote || paymentText,
          qrImageUrl: detail.qrImageUrl,
          upiUrl: detail.upiUrl,
        }
      : methods
        ? { ...methods, payeeName: PAYMENT_BRAND, paymentNote: PAYMENT_BRAND }
        : null

  const pending = (data?.payments ?? []).filter((p) => p.paymentStatus === 'pending')

  return (
    <div>
      {hasDetail ? (
        <Button asChild variant="ghost" className="mb-4 px-0">
          <Link to="/member/payments">
            <ArrowLeft className="h-4 w-4" /> Back to payments
          </Link>
        </Button>
      ) : null}

      <PageHeader
        title={hasDetail ? `Pay — ${detail?.groupName ?? '…'}` : t('member.paymentTitle')}
        description={
          hasDetail
            ? `Month ${m}`
            : t('member.paymentDesc')
        }
      />

      {isLoading && !hasDetail ? <p className="text-sm text-muted-foreground">Loading…</p> : null}
      {error ? <p className="text-sm text-destructive">{(error as Error).message}</p> : null}

      {hasDetail ? (
        detail ? (
          <Card className="mb-4">
            <CardHeader>
              <CardTitle>{formatInr(detail.amount)}</CardTitle>
              <CardDescription>
                Winner: {detail.winnerName ?? '—'} · {detail.memberName}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <Badge variant={detail.paymentStatus === 'paid' ? 'success' : 'warning'}>
                {detail.paymentStatus}
              </Badge>
              {detail.paymentStatus === 'paid' ? (
                <p className="text-muted-foreground">{t('member.alreadyPaid')}</p>
              ) : (
                <ol className="list-decimal space-y-1 pl-4 text-muted-foreground">
                  <li>{t('member.payStepScan')}</li>
                  <li>{t('member.payStepRemark', { remark: paymentText })}</li>
                  <li>{t('member.payStepUtr')}</li>
                </ol>
              )}
            </CardContent>
          </Card>
        ) : (
          <p className="mb-4 text-sm text-muted-foreground">Loading payment…</p>
        )
      ) : null}

      <UpiQrCard
        methods={detailMethods}
        title={PAYMENT_BRAND}
        paymentText={paymentText}
        amount={
          hasDetail && detail && detail.paymentStatus !== 'paid' ? detail.amount : null
        }
        amountLabel={
          hasDetail && detail && detail.paymentStatus !== 'paid' ? formatInr(detail.amount) : undefined
        }
      />

      {hasDetail && detail && detail.paymentStatus !== 'paid' ? (
        <Card className="mb-6">
          <CardHeader>
            <CardTitle className="text-base">{t('member.utrLabel')}</CardTitle>
            <CardDescription>{t('member.utrHint')}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="space-y-1.5">
              <Label htmlFor="member-utr">{t('member.utrLabel')}</Label>
              <Input
                id="member-utr"
                placeholder={t('member.utrPlaceholder')}
                value={utr}
                onChange={(e) => setUtr(e.target.value)}
              />
            </div>
            <Button
              disabled={submitUtr.isPending || utr.trim().length < 6}
              onClick={() => submitUtr.mutate()}
            >
              {submitUtr.isPending ? t('common.saving') : t('member.submitUtr')}
            </Button>
            {utrMessage ? <p className="text-sm text-emerald-700">{utrMessage}</p> : null}
            {utrError ? <p className="text-sm text-destructive">{utrError}</p> : null}
          </CardContent>
        </Card>
      ) : null}

      {!hasDetail && pending.length > 0 ? (
        <Card className="mb-6">
          <CardHeader>
            <CardTitle>{t('member.payNow')}</CardTitle>
            <CardDescription>{pending.length} pending month{pending.length === 1 ? '' : 's'}</CardDescription>
          </CardHeader>
          <CardContent className="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
            {pending.slice(0, 6).map((p) => (
              <div key={p.id} className="rounded-lg border p-3 text-sm">
                <p className="font-medium">{p.groupName}</p>
                <p className="text-muted-foreground">
                  Month {p.monthNumber} · {formatInr(p.paymentAmount)}
                  {p.handLabel || p.memberNumber
                    ? ` · #${p.memberNumber ?? '?'}${p.handLabel ? ` ${p.handLabel}` : ''}`
                    : ''}
                </p>
                <Button asChild size="sm" className="mt-2 w-full">
                  <Link to={`/member/payments/${p.groupId}/${p.monthNumber}`}>Pay</Link>
                </Button>
              </div>
            ))}
          </CardContent>
        </Card>
      ) : null}

      {data ? (
        <>
          {!hasDetail ? (
            <div className="mb-6 grid gap-4 sm:grid-cols-2">
              <StatCard label={t('member.pendingDues')} value={formatInr(data.totalPending)} />
              <StatCard label={t('member.totalPaid')} value={formatInr(data.totalPaid)} />
            </div>
          ) : null}

          <Card>
            <CardHeader>
              <CardTitle>{t('member.paymentHistory')}</CardTitle>
              <CardDescription>
                Filter and browse your dues. {filtered.length} matching row
                {filtered.length === 1 ? '' : 's'}.
              </CardDescription>

              <div className="mt-4 grid gap-3 rounded-xl border border-border bg-muted/40 p-3 sm:grid-cols-2 lg:grid-cols-4">
                <div className="space-y-1.5">
                  <Label htmlFor="m-search">Search</Label>
                  <div className="relative">
                    <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                      id="m-search"
                      className="pl-8"
                      placeholder="Group or winner"
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                    />
                  </div>
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="m-group">Group</Label>
                  <select
                    id="m-group"
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    value={groupFilter}
                    onChange={(e) => setGroupFilter(e.target.value)}
                  >
                    <option value="all">All groups</option>
                    {groups.map(([id, name]) => (
                      <option key={id} value={String(id)}>
                        {name}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="m-month">Month</Label>
                  <select
                    id="m-month"
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    value={monthFilter}
                    onChange={(e) => setMonthFilter(e.target.value)}
                  >
                    <option value="all">All months</option>
                    {months.map((mo) => (
                      <option key={mo} value={String(mo)}>
                        Month {mo}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="space-y-1.5">
                  <div className="flex items-center justify-between">
                    <Label htmlFor="m-status">Status</Label>
                    {hasFilters ? (
                      <button
                        type="button"
                        className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                        onClick={clearFilters}
                      >
                        <X className="h-3 w-3" />
                        Clear
                      </button>
                    ) : null}
                  </div>
                  <select
                    id="m-status"
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                  >
                    <option value="all">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="paid">Paid</option>
                    <option value="failed">Failed</option>
                  </select>
                </div>
              </div>
            </CardHeader>

            <CardContent>
              <div className="overflow-x-auto rounded-xl border border-border">
                <table className="w-full min-w-[760px] border-collapse text-left text-sm">
                  <thead>
                    <tr className="border-b border-border bg-muted/60">
                      <th className="px-3 py-3 font-semibold">Group</th>
                      <th className="px-3 py-3 font-semibold">Month</th>
                      <th className="px-3 py-3 text-right font-semibold">Amount</th>
                      <th className="px-3 py-3 font-semibold">Status</th>
                      <th className="px-3 py-3 font-semibold">Winner</th>
                      <th className="px-3 py-3 font-semibold">Paid on</th>
                      <th className="px-3 py-3 text-right font-semibold"> </th>
                    </tr>
                  </thead>
                  <tbody>
                    {pageRows.map((p) => (
                      <tr key={p.id} className="border-b border-border last:border-0 hover:bg-muted/40">
                        <td className="px-3 py-2.5 font-medium">
                          {p.groupName}
                          {p.handLabel || p.memberNumber != null ? (
                            <span className="mt-0.5 block text-xs font-normal text-muted-foreground">
                              #{p.memberNumber ?? '?'}
                              {p.handLabel ? ` · ${p.handLabel}` : ''}
                            </span>
                          ) : null}
                        </td>
                        <td className="px-3 py-2.5">
                          <span className="inline-flex h-7 min-w-7 items-center justify-center rounded-md bg-teal-soft px-2 text-xs font-semibold text-primary">
                            M{p.monthNumber}
                          </span>
                        </td>
                        <td className="px-3 py-2.5 text-right font-medium tabular-nums">
                          {formatInr(p.paymentAmount)}
                        </td>
                        <td className="px-3 py-2.5">
                          <Badge variant={statusVariant(p.paymentStatus)}>{p.paymentStatus}</Badge>
                        </td>
                        <td className="px-3 py-2.5 text-muted-foreground">{p.winnerName ?? '—'}</td>
                        <td className="px-3 py-2.5 text-muted-foreground">
                          {p.paymentDate ? new Date(p.paymentDate).toLocaleDateString('en-IN') : '—'}
                        </td>
                        <td className="px-3 py-2.5 text-right">
                          <div className="flex justify-end gap-1">
                            {p.paymentStatus === 'pending' ? (
                              <Button asChild size="sm">
                                <Link to={`/member/payments/${p.groupId}/${p.monthNumber}`}>Pay</Link>
                              </Button>
                            ) : null}
                            <Button asChild size="sm" variant="ghost">
                              <Link to={`/member/groups/${p.groupId}`}>Ledger</Link>
                            </Button>
                          </div>
                        </td>
                      </tr>
                    ))}
                    {pageRows.length === 0 ? (
                      <tr>
                        <td colSpan={7} className="px-3 py-10 text-center text-muted-foreground">
                          {hasFilters ? 'No payments match the current filters.' : 'No payment records yet.'}
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>

              <div className="mt-4">
                <TablePagination
                  page={safePage}
                  pageSize={pageSize}
                  total={filtered.length}
                  onPageChange={setPage}
                  onPageSizeChange={setPageSize}
                />
              </div>
            </CardContent>
          </Card>
        </>
      ) : null}
    </div>
  )
}

/** Legacy deep links → unified payments routes */
export function MemberPayRedirect() {
  const { groupId, month } = useParams()
  if (groupId && month) return <Navigate to={`/member/payments/${groupId}/${month}`} replace />
  return <Navigate to="/member/payments" replace />
}
