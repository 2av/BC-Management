import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { Search, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { formatInr, type GroupListItem } from '@/features/groups/types'
import type { GroupPaymentsOverview, PaymentItem } from '@/features/payments/types'
import { PageHeader } from '@/components/layout/PageHeader'
import { StatCard } from '@/components/layout/StatCard'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { TablePagination } from '@/components/ui/table-pagination'

function statusVariant(status: string) {
  if (status === 'paid') return 'success' as const
  if (status === 'pending') return 'warning' as const
  return 'muted' as const
}

export function AdminPaymentsPage() {
  const { t } = useTranslation()
  const api = useApi()
  const qc = useQueryClient()
  const [groupId, setGroupId] = useState<number | null>(null)
  const [monthFilter, setMonthFilter] = useState('all')
  const [statusFilter, setStatusFilter] = useState('all')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const { data: groups } = useQuery({
    queryKey: ['groups'],
    queryFn: () => api.get<GroupListItem[]>('/api/groups'),
  })

  const activeGroupId = groupId ?? groups?.[0]?.id ?? null

  const { data, isLoading } = useQuery({
    queryKey: ['group-payments', activeGroupId],
    queryFn: () => api.get<GroupPaymentsOverview>(`/api/groups/${activeGroupId}/payments`),
    enabled: !!activeGroupId,
  })

  const markPaid = useMutation({
    mutationFn: (id: number) => api.patch(`/api/payments/${id}`, { paymentStatus: 'paid' }),
    onSuccess: async () => {
      setMessage('Marked as paid.')
      setError(null)
      await qc.invalidateQueries({ queryKey: ['group-payments', activeGroupId] })
      await qc.invalidateQueries({ queryKey: ['groups'] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const bulkPaid = useMutation({
    mutationFn: (monthNumber: number) =>
      api.post<{ message?: string }>(`/api/groups/${activeGroupId}/payments/bulk-mark-paid`, { monthNumber }),
    onSuccess: async (res: { message?: string }) => {
      setMessage(res.message ?? 'Bulk update done.')
      setError(null)
      await qc.invalidateQueries({ queryKey: ['group-payments', activeGroupId] })
      await qc.invalidateQueries({ queryKey: ['groups'] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const months = useMemo(() => {
    const set = new Set(data?.payments.map((p) => p.monthNumber) ?? [])
    return Array.from(set).sort((a, b) => a - b)
  }, [data])

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase()
    return (data?.payments ?? []).filter((p) => {
      if (monthFilter !== 'all' && p.monthNumber !== Number(monthFilter)) return false
      if (statusFilter !== 'all' && p.paymentStatus !== statusFilter) return false
      if (!q) return true
      return (
        p.memberName.toLowerCase().includes(q) ||
        String(p.memberNumber).includes(q) ||
        (p.winnerName?.toLowerCase().includes(q) ?? false)
      )
    })
  }, [data, monthFilter, statusFilter, search])

  const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize))
  const safePage = Math.min(page, totalPages)
  const pageRows = filtered.slice((safePage - 1) * pageSize, safePage * pageSize)

  useEffect(() => {
    setPage(1)
  }, [activeGroupId, monthFilter, statusFilter, search, pageSize])

  const hasFilters = monthFilter !== 'all' || statusFilter !== 'all' || search.trim() !== ''

  function clearFilters() {
    setMonthFilter('all')
    setStatusFilter('all')
    setSearch('')
  }

  function onGroupChange(id: number) {
    setGroupId(id)
    clearFilters()
  }

  return (
    <div>
      <PageHeader
        title={t('pages.paymentsTitle')}
        description={t('pages.paymentsDesc')}
        actions={<Badge>Admin</Badge>}
      />

      {message ? <p className="mb-3 text-sm text-emerald-700">{message}</p> : null}
      {error ? <p className="mb-3 text-sm text-destructive">{error}</p> : null}

      <div className="mb-4 flex flex-wrap gap-2">
        {groups?.map((g) => (
          <Button
            key={g.id}
            size="sm"
            variant={activeGroupId === g.id ? 'default' : 'outline'}
            onClick={() => onGroupChange(g.id)}
          >
            {g.groupName}
          </Button>
        ))}
      </div>

      {!activeGroupId ? (
        <p className="text-sm text-muted-foreground">No groups available.</p>
      ) : (
        <>
          {data ? (
            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <StatCard label={t('pages.pendingCount')} value={`${data.pendingCount}`} />
              <StatCard label={t('pages.pendingAmount')} value={formatInr(data.pendingAmount)} />
              <StatCard label={t('pages.paidCount')} value={`${data.paidCount}`} />
              <StatCard label={t('pages.paidAmount')} value={formatInr(data.paidAmount)} />
            </div>
          ) : null}

          <Card>
            <CardHeader>
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <CardTitle>{data?.groupName ?? 'Group payments'}</CardTitle>
                  <CardDescription>
                    Filter by member, month, or status. {filtered.length} matching row
                    {filtered.length === 1 ? '' : 's'}.
                  </CardDescription>
                </div>
                <Button asChild size="sm" variant="outline">
                  <Link to={`/admin/groups/${activeGroupId}`}>Open ledger</Link>
                </Button>
              </div>

              <div className="mt-4 grid gap-3 rounded-xl border border-border bg-muted/40 p-3 sm:grid-cols-2 lg:grid-cols-4">
                <div className="space-y-1.5 sm:col-span-2 lg:col-span-1">
                  <Label htmlFor="pay-search">Search member</Label>
                  <div className="relative">
                    <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                      id="pay-search"
                      className="pl-8"
                      placeholder="Name or member #"
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                    />
                  </div>
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="pay-month">Month</Label>
                  <select
                    id="pay-month"
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    value={monthFilter}
                    onChange={(e) => setMonthFilter(e.target.value)}
                  >
                    <option value="all">All months</option>
                    {months.map((m) => (
                      <option key={m} value={String(m)}>
                        Month {m}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="pay-status">Status</Label>
                  <select
                    id="pay-status"
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
                <div className="flex items-end gap-2">
                  {monthFilter !== 'all' ? (
                    <Button
                      size="sm"
                      variant="secondary"
                      className="flex-1"
                      disabled={bulkPaid.isPending}
                      onClick={() => bulkPaid.mutate(Number(monthFilter))}
                    >
                      Mark month paid
                    </Button>
                  ) : null}
                  {hasFilters ? (
                    <Button size="sm" variant="ghost" onClick={clearFilters}>
                      <X className="h-4 w-4" />
                      Clear
                    </Button>
                  ) : null}
                </div>
              </div>
            </CardHeader>

            <CardContent>
              {isLoading ? <p className="mb-3 text-sm text-muted-foreground">Loading payments…</p> : null}

              <div className="overflow-x-auto rounded-xl border border-border">
                <table className="w-full min-w-[960px] border-collapse text-left text-sm">
                  <thead>
                    <tr className="border-b border-border bg-muted/60">
                      <th className="px-3 py-3 font-semibold text-foreground">Month</th>
                      <th className="px-3 py-3 font-semibold text-foreground">Member</th>
                      <th className="px-3 py-3 text-right font-semibold text-foreground">Expected</th>
                      <th className="px-3 py-3 text-right font-semibold text-foreground">Amount</th>
                      <th className="px-3 py-3 font-semibold text-foreground">Status</th>
                      <th className="px-3 py-3 font-semibold text-foreground">Winner</th>
                      <th className="px-3 py-3 font-semibold text-foreground">Paid on</th>
                      <th className="px-3 py-3 text-right font-semibold text-foreground">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {pageRows.map((p) => (
                      <PaymentRow
                        key={p.id}
                        payment={p}
                        busy={markPaid.isPending}
                        onMarkPaid={() => markPaid.mutate(p.id)}
                      />
                    ))}
                    {!isLoading && pageRows.length === 0 ? (
                      <tr>
                        <td colSpan={8} className="px-3 py-10 text-center text-muted-foreground">
                          {hasFilters
                            ? 'No payments match the current filters.'
                            : 'No payment rows yet. Approve a bidding winner to seed pending dues.'}
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
      )}
    </div>
  )
}

function PaymentRow({
  payment: p,
  busy,
  onMarkPaid,
}: {
  payment: PaymentItem
  busy: boolean
  onMarkPaid: () => void
}) {
  return (
    <tr className="border-b border-border last:border-0 hover:bg-muted/40">
      <td className="px-3 py-2.5">
        <span className="inline-flex h-7 min-w-7 items-center justify-center rounded-md bg-teal-soft px-2 text-xs font-semibold text-primary">
          M{p.monthNumber}
        </span>
      </td>
      <td className="px-3 py-2.5">
        <p className="font-medium text-foreground">{p.memberName}</p>
        <p className="text-xs text-muted-foreground">
          Member #{p.memberNumber}
          {p.handLabel ? ` · ${p.handLabel}` : ''}
        </p>
      </td>
      <td className="px-3 py-2.5 text-right tabular-nums text-muted-foreground">{formatInr(p.expectedAmount)}</td>
      <td className="px-3 py-2.5 text-right font-medium tabular-nums">{formatInr(p.paymentAmount)}</td>
      <td className="px-3 py-2.5">
        <Badge variant={statusVariant(p.paymentStatus)}>{p.paymentStatus}</Badge>
      </td>
      <td className="px-3 py-2.5 text-muted-foreground">{p.winnerName ?? '—'}</td>
      <td className="px-3 py-2.5 text-muted-foreground">
        {p.paymentDate ? new Date(p.paymentDate).toLocaleDateString('en-IN') : '—'}
      </td>
      <td className="px-3 py-2.5 text-right">
        {p.paymentStatus === 'pending' ? (
          <Button size="sm" onClick={onMarkPaid} disabled={busy}>
            Mark paid
          </Button>
        ) : (
          <span className="text-xs text-muted-foreground">Done</span>
        )}
      </td>
    </tr>
  )
}
