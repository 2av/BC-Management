import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { Search, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { formatInr, type GroupListItem } from '@/features/groups/types'
import type { GroupPaymentsOverview, PaymentItem } from '@/features/payments/types'
import type { GroupMemberRosterItem } from '@/features/members/types'
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
  const [groupStatusTab, setGroupStatusTab] = useState<'active' | 'completed'>('active')
  const [groupId, setGroupId] = useState<number | null>(null)
  const [monthFilter, setMonthFilter] = useState('all')
  const [statusFilter, setStatusFilter] = useState('all')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [addSeatIds, setAddSeatIds] = useState<number[]>([])
  const [addMonth, setAddMonth] = useState('1')
  const [addAmount, setAddAmount] = useState('')
  const [addDate, setAddDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [addStatus, setAddStatus] = useState('paid')
  const [dueMonth, setDueMonth] = useState('1')
  const [dueAmount, setDueAmount] = useState('')

  const { data: groups } = useQuery({
    queryKey: ['groups'],
    queryFn: () => api.get<GroupListItem[]>('/api/groups'),
  })

  const activeCount = useMemo(() => groups?.filter((g) => g.status === 'active').length ?? 0, [groups])
  const completedCount = useMemo(
    () => groups?.filter((g) => g.status === 'completed').length ?? 0,
    [groups],
  )
  const tabGroups = useMemo(
    () => (groups ?? []).filter((g) => g.status === groupStatusTab),
    [groups, groupStatusTab],
  )

  const activeGroupId = groupId && tabGroups.some((g) => g.id === groupId) ? groupId : (tabGroups[0]?.id ?? null)
  const activeGroup = tabGroups.find((g) => g.id === activeGroupId) ?? null

  const { data, isLoading } = useQuery({
    queryKey: ['group-payments', activeGroupId],
    queryFn: () => api.get<GroupPaymentsOverview>(`/api/groups/${activeGroupId}/payments`),
    enabled: !!activeGroupId,
  })

  const { data: roster } = useQuery({
    queryKey: ['group-roster', activeGroupId],
    queryFn: () => api.get<GroupMemberRosterItem[]>(`/api/groups/${activeGroupId}/members`),
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

  const addPayment = useMutation({
    mutationFn: () =>
      api.post<{ message?: string; count?: number }>(`/api/groups/${activeGroupId}/payments`, {
        groupMemberIds: addSeatIds,
        monthNumber: Number(addMonth),
        paymentAmount: Number(addAmount),
        paymentStatus: addStatus,
        paymentDate: addDate || null,
      }),
    onSuccess: async (res: { message?: string }) => {
      setMessage(res.message ?? 'Payment added.')
      setError(null)
      setAddSeatIds([])
      await qc.invalidateQueries({ queryKey: ['group-payments', activeGroupId] })
      await qc.invalidateQueries({ queryKey: ['groups'] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const setDueMutation = useMutation({
    mutationFn: (paymentAmount: number | null) =>
      api.post<{ message?: string }>(`/api/groups/${activeGroupId}/payments/set-month-amount`, {
        monthNumber: Number(dueMonth),
        paymentAmount,
      }),
    onSuccess: async (res: { message?: string }) => {
      setMessage(res.message ?? 'Month due amount saved.')
      setError(null)
      await qc.invalidateQueries({ queryKey: ['group-payments', activeGroupId] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const months = useMemo(() => {
    const set = new Set(data?.payments.map((p) => p.monthNumber) ?? [])
    return Array.from(set).sort((a, b) => a - b)
  }, [data])

  const monthOptions = useMemo(() => {
    const total = activeGroup?.totalMembers ?? months[months.length - 1] ?? 1
    return Array.from({ length: total }, (_, i) => i + 1)
  }, [activeGroup, months])

  const paymentBySeatForMonth = useMemo(() => {
    const monthNum = Number(addMonth)
    const map = new Map<number, PaymentItem>()
    for (const p of data?.payments ?? []) {
      if (p.monthNumber !== monthNum || p.groupMemberId == null) continue
      map.set(p.groupMemberId, p)
    }
    return map
  }, [data, addMonth])

  const activeRoster = useMemo(
    () => (roster ?? []).filter((r) => r.status === 'active'),
    [roster],
  )

  const selectableSeatIds = useMemo(
    () => activeRoster.filter((r) => !paymentBySeatForMonth.has(r.groupMemberId)).map((r) => r.groupMemberId),
    [activeRoster, paymentBySeatForMonth],
  )

  const suggestedAmount = useMemo(() => {
    const monthNum = Number(addMonth)
    const due = data?.monthDues?.find((m) => m.monthNumber === monthNum)
    if (due) return due.effectiveAmount
    const fromRow = data?.payments.find((p) => p.monthNumber === monthNum)
    if (fromRow) return fromRow.expectedAmount
    return data?.monthlyContribution ?? activeGroup?.monthlyContribution ?? 0
  }, [addMonth, data, activeGroup])

  useEffect(() => {
    if (suggestedAmount > 0) setAddAmount(String(suggestedAmount))
  }, [suggestedAmount, activeGroupId, addMonth])

  useEffect(() => {
    setAddSeatIds([])
    setAddMonth('1')
    setAddAmount('')
    setAddStatus('paid')
    setDueMonth('1')
    setDueAmount('')
  }, [activeGroupId])

  useEffect(() => {
    const row = data?.monthDues?.find((m) => m.monthNumber === Number(dueMonth))
    if (!row) {
      setDueAmount('')
      return
    }
    setDueAmount(row.paymentDueAmount != null ? String(row.paymentDueAmount) : '')
  }, [data, dueMonth, activeGroupId])

  useEffect(() => {
    setAddSeatIds((prev) => prev.filter((id) => selectableSeatIds.includes(id)))
  }, [selectableSeatIds])

  const dueEffective = useMemo(() => {
    const row = data?.monthDues?.find((m) => m.monthNumber === Number(dueMonth))
    return row?.effectiveAmount ?? data?.monthlyContribution ?? activeGroup?.monthlyContribution ?? 0
  }, [data, dueMonth, activeGroup])

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

  function onGroupStatusTab(tab: 'active' | 'completed') {
    setGroupStatusTab(tab)
    setGroupId(null)
    clearFilters()
  }

  function toggleSeat(id: number, disabled: boolean) {
    if (disabled) return
    setAddSeatIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]))
  }

  function selectAllAvailable() {
    setAddSeatIds(selectableSeatIds)
  }

  function clearSeatSelection() {
    setAddSeatIds([])
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

      <div className="mb-3 flex flex-wrap gap-2">
        <Button
          size="sm"
          variant={groupStatusTab === 'active' ? 'default' : 'outline'}
          onClick={() => onGroupStatusTab('active')}
        >
          Active ({activeCount})
        </Button>
        <Button
          size="sm"
          variant={groupStatusTab === 'completed' ? 'default' : 'outline'}
          onClick={() => onGroupStatusTab('completed')}
        >
          Completed ({completedCount})
        </Button>
      </div>

      <div className="mb-4 flex flex-wrap gap-2">
        {tabGroups.map((g) => (
          <Button
            key={g.id}
            size="sm"
            variant={activeGroupId === g.id ? 'default' : 'outline'}
            onClick={() => onGroupChange(g.id)}
          >
            {g.groupName}
          </Button>
        ))}
        {!tabGroups.length ? (
          <p className="text-sm text-muted-foreground">
            {groupStatusTab === 'active' ? 'No active groups.' : 'No completed groups.'}
          </p>
        ) : null}
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

          <Card className="mb-6">
            <CardHeader>
              <CardTitle>Set month payment amount</CardTitle>
              <CardDescription>
                Fixed due for bid / random months. Members see this amount on group Pay QR. Leave blank to use monthly
                BC amount ({formatInr(data?.monthlyContribution ?? activeGroup?.monthlyContribution ?? 0)}).
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form
                className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                onSubmit={(e) => {
                  e.preventDefault()
                  if (dueAmount.trim() !== '' && Number(dueAmount) <= 0) {
                    setError('Enter an amount greater than 0, or clear the field for BC default.')
                    return
                  }
                  setDueMutation.mutate(dueAmount.trim() === '' ? null : Number(dueAmount))
                }}
              >
                <div className="space-y-1.5">
                  <Label htmlFor="due-month">Month</Label>
                  <select
                    id="due-month"
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    value={dueMonth}
                    onChange={(e) => setDueMonth(e.target.value)}
                  >
                    {monthOptions.map((m) => (
                      <option key={m} value={String(m)}>
                        Month {m}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="due-amount">Due amount (₹)</Label>
                  <Input
                    id="due-amount"
                    type="number"
                    min={1}
                    step="0.01"
                    placeholder={`Default ${dueEffective}`}
                    value={dueAmount}
                    onChange={(e) => setDueAmount(e.target.value)}
                  />
                </div>
                <div className="flex items-end gap-2 sm:col-span-2">
                  <Button type="submit" disabled={setDueMutation.isPending || !activeGroupId}>
                    {setDueMutation.isPending ? 'Saving…' : 'Save amount'}
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    disabled={setDueMutation.isPending}
                    onClick={() => {
                      setDueAmount('')
                      setDueMutation.mutate(null)
                    }}
                  >
                    Use BC default
                  </Button>
                </div>
                <p className="text-xs text-muted-foreground sm:col-span-2 lg:col-span-4">
                  Members currently pay{' '}
                  <strong>{formatInr(dueEffective)}</strong> for month {dueMonth}
                  {data?.monthDues?.find((m) => m.monthNumber === Number(dueMonth))?.paymentDueAmount != null
                    ? ' (admin fixed)'
                    : ' (BC / bid default)'}
                  .
                </p>
              </form>
            </CardContent>
          </Card>

          <Card className="mb-6">
            <CardHeader>
              <CardTitle>Add payment</CardTitle>
              <CardDescription>
                Select one or more member seats. Seats already paid for the chosen month are disabled.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form
                className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6"
                onSubmit={(e) => {
                  e.preventDefault()
                  if (addSeatIds.length === 0) {
                    setError('Select at least one member seat.')
                    return
                  }
                  if (!addAmount || Number(addAmount) <= 0) {
                    setError('Enter a payment amount greater than 0.')
                    return
                  }
                  addPayment.mutate()
                }}
              >
                <div className="space-y-1.5 sm:col-span-2 lg:col-span-3">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <Label>Member seats</Label>
                    <div className="flex gap-2">
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={selectAllAvailable}
                        disabled={selectableSeatIds.length === 0}
                      >
                        Select available
                      </Button>
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={clearSeatSelection}
                        disabled={addSeatIds.length === 0}
                      >
                        Clear
                      </Button>
                    </div>
                  </div>
                  <div className="max-h-48 space-y-1 overflow-y-auto rounded-md border border-input bg-background p-2">
                    {activeRoster.length === 0 ? (
                      <p className="px-1 py-2 text-sm text-muted-foreground">No active seats in this group.</p>
                    ) : (
                      activeRoster.map((r) => {
                        const existing = paymentBySeatForMonth.get(r.groupMemberId)
                        const paid = existing?.paymentStatus === 'paid'
                        const blocked = !!existing
                        const checked = addSeatIds.includes(r.groupMemberId)
                        const labelExtra = paid
                          ? ' — paid'
                          : existing
                            ? ` — ${existing.paymentStatus}`
                            : ''
                        return (
                          <label
                            key={r.groupMemberId}
                            className={`flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm ${
                              blocked ? 'cursor-not-allowed opacity-50' : 'hover:bg-muted/60'
                            }`}
                          >
                            <input
                              type="checkbox"
                              className="h-4 w-4 accent-primary"
                              checked={checked}
                              disabled={blocked}
                              onChange={() => toggleSeat(r.groupMemberId, blocked)}
                            />
                            <span className={blocked ? 'text-muted-foreground' : 'text-foreground'}>
                              {r.memberName}
                              {r.handLabel ? ` · ${r.handLabel}` : ''} (#{r.memberNumber})
                              {labelExtra}
                            </span>
                          </label>
                        )
                      })
                    )}
                  </div>
                  <p className="text-xs text-muted-foreground">
                    {addSeatIds.length} selected
                    {selectableSeatIds.length ? ` · ${selectableSeatIds.length} available` : ''}
                  </p>
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="add-month">Month</Label>
                  <select
                    id="add-month"
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    value={addMonth}
                    onChange={(e) => setAddMonth(e.target.value)}
                  >
                    {monthOptions.map((m) => (
                      <option key={m} value={String(m)}>
                        Month {m}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="add-amount">Amount (₹)</Label>
                  <Input
                    id="add-amount"
                    type="number"
                    min={1}
                    step="0.01"
                    value={addAmount}
                    onChange={(e) => setAddAmount(e.target.value)}
                    required
                  />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="add-date">Date</Label>
                  <Input
                    id="add-date"
                    type="date"
                    value={addDate}
                    onChange={(e) => setAddDate(e.target.value)}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="add-status">Status</Label>
                  <select
                    id="add-status"
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    value={addStatus}
                    onChange={(e) => setAddStatus(e.target.value)}
                  >
                    <option value="paid">Paid</option>
                    <option value="pending">Pending</option>
                    <option value="failed">Failed</option>
                  </select>
                </div>
                <div className="flex items-end sm:col-span-2 lg:col-span-6">
                  <Button type="submit" disabled={addPayment.isPending || !activeGroupId || addSeatIds.length === 0}>
                    {addPayment.isPending
                      ? 'Saving…'
                      : addSeatIds.length > 1
                        ? `Add ${addSeatIds.length} payments`
                        : 'Add payment'}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>

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
                      <th className="px-3 py-3 font-semibold text-foreground">UTR</th>
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
                        <td colSpan={9} className="px-3 py-10 text-center text-muted-foreground">
                          {hasFilters
                            ? 'No payments match the current filters.'
                            : 'No payment rows yet. Use Add payment above, or approve a bidding winner to seed pending dues.'}
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
      <td className="px-3 py-2.5 font-mono text-xs text-muted-foreground">{p.transactionId ?? '—'}</td>
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
