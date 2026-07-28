import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { useEffect, useMemo, useState } from 'react'
import { ArrowLeft, Gavel } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { formatInr } from '@/features/groups/types'
import type { BidItem, GroupBiddingOverview } from '@/features/bidding/types'
import type { GroupMemberRosterItem } from '@/features/members/types'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

function statusVariant(status: string) {
  if (status === 'open') return 'success' as const
  if (status === 'closed') return 'warning' as const
  if (status === 'completed') return 'default' as const
  return 'muted' as const
}

export function AdminBiddingPage() {
  const { t } = useTranslation()
  const { groupId } = useParams()
  const id = Number(groupId)
  const api = useApi()
  const qc = useQueryClient()
  const [selectedMonth, setSelectedMonth] = useState<number | null>(null)
  const [openForm, setOpenForm] = useState<{ month: number; endDate: string } | null>(null)
  const [manualSeatId, setManualSeatId] = useState('')
  const [manualBoli, setManualBoli] = useState('')
  const [manualMode, setManualMode] = useState<'boli' | 'random'>('boli')
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['bidding', id],
    queryFn: () => api.get<GroupBiddingOverview>(`/api/groups/${id}/bidding`),
    enabled: id > 0,
  })

  const { data: bids } = useQuery({
    queryKey: ['month-bids', id, selectedMonth],
    queryFn: () => api.get<BidItem[]>(`/api/groups/${id}/bidding/months/${selectedMonth}/bids`),
    enabled: id > 0 && selectedMonth != null,
  })

  const { data: roster } = useQuery({
    queryKey: ['group-members', id],
    queryFn: () => api.get<GroupMemberRosterItem[]>(`/api/groups/${id}/members`),
    enabled: id > 0,
  })

  const openMutation = useMutation({
    mutationFn: (body: { monthNumber: number; endDate: string }) =>
      api.post(`/api/groups/${id}/bidding/open`, body),
    onSuccess: async () => {
      setMessage('Bidding opened using BC chart boli start for this month.')
      setError(null)
      setOpenForm(null)
      await qc.invalidateQueries({ queryKey: ['bidding', id] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const closeMutation = useMutation({
    mutationFn: (monthNumber: number) => api.post(`/api/groups/${id}/bidding/months/${monthNumber}/close`),
    onSuccess: async () => {
      setMessage('Bidding closed.')
      await qc.invalidateQueries({ queryKey: ['bidding', id] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const approveMutation = useMutation({
    mutationFn: (body: {
      monthNumber: number
      winnerMemberId: number
      winnerGroupMemberId?: number | null
      winningBidAmount: number
      boliAmount?: number | null
      useRandomAmount?: boolean
    }) => api.post(`/api/groups/${id}/bidding/approve-winner`, body),
    onSuccess: async () => {
      setMessage('Winner approved. Ledger updated.')
      setError(null)
      setManualSeatId('')
      setManualBoli('')
      await qc.invalidateQueries({ queryKey: ['bidding', id] })
      await qc.invalidateQueries({ queryKey: ['month-bids', id, selectedMonth] })
      await qc.invalidateQueries({ queryKey: ['group-ledger', id] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const allocateOrganiserMutation = useMutation({
    mutationFn: () => api.post<{ message?: string }>(`/api/groups/${id}/bidding/allocate-organiser`),
    onSuccess: async (res) => {
      setMessage(res.message ?? 'Month 1 pot assigned to organiser.')
      setError(null)
      await qc.invalidateQueries({ queryKey: ['bidding', id] })
      await qc.invalidateQueries({ queryKey: ['group-ledger', id] })
      await qc.invalidateQueries({ queryKey: ['groups'] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const month1 = data?.months.find((m) => m.monthNumber === 1)

  /**
   * Hide months that are fully settled: payments complete AND pot already assigned (winner set).
   * If everyone paid but no winner yet, keep the month visible so admin can View / set winner.
   */
  const lastSettledMonth = useMemo(() => {
    const settled = (data?.months ?? [])
      .filter(
        (m) =>
          m.paymentDone &&
          (Boolean(m.winnerMemberId) || Boolean(m.winnerGroupMemberId) || m.biddingStatus === 'completed'),
      )
      .map((m) => m.monthNumber)
    return settled.length ? Math.max(...settled) : 0
  }, [data?.months])

  const visibleMonths = useMemo(
    () =>
      (data?.months ?? []).filter((m) => {
        const hasWinner =
          Boolean(m.winnerMemberId) || Boolean(m.winnerGroupMemberId) || m.biddingStatus === 'completed'
        // Always show months that still need a winner (even if paymentDone).
        if (!hasWinner) return true
        return m.monthNumber > lastSettledMonth
      }),
    [data?.months, lastSettledMonth],
  )

  const settledMonthsSummary = useMemo(
    () =>
      (data?.months ?? []).filter(
        (m) =>
          m.monthNumber <= lastSettledMonth &&
          m.paymentDone &&
          (Boolean(m.winnerMemberId) || Boolean(m.winnerGroupMemberId) || m.biddingStatus === 'completed'),
      ),
    [data?.months, lastSettledMonth],
  )

  useEffect(() => {
    if (!visibleMonths.length) {
      setSelectedMonth(null)
      return
    }
    if (selectedMonth == null || !visibleMonths.some((m) => m.monthNumber === selectedMonth)) {
      const prefer =
        visibleMonths.find((m) => m.biddingStatus === 'open') ??
        visibleMonths.find((m) => m.monthNumber > 1 && !m.winnerMemberId) ??
        visibleMonths.find((m) => m.monthNumber > 1) ??
        visibleMonths[0]
      setSelectedMonth(prefer.monthNumber > 1 ? prefer.monthNumber : null)
    }
  }, [visibleMonths, selectedMonth])

  const selectedMonthMeta = data?.months.find((m) => m.monthNumber === selectedMonth)

  const wonSeatIds = useMemo(() => {
    const ids = new Set<number>()
    for (const m of data?.months ?? []) {
      if (m.winnerGroupMemberId) ids.add(m.winnerGroupMemberId)
    }
    return ids
  }, [data?.months])

  const eligibleSeats = useMemo(() => {
    return (roster ?? [])
      .filter((s) => s.status === 'active' && !wonSeatIds.has(s.groupMemberId))
      .slice()
      .sort((a, b) => a.memberNumber - b.memberNumber)
  }, [roster, wonSeatIds])

  const selectedMonthStatus = selectedMonthMeta?.biddingStatus
  const selectedHasWinner =
    Boolean(selectedMonthMeta?.winnerMemberId) ||
    Boolean(selectedMonthMeta?.winnerGroupMemberId) ||
    selectedMonthStatus === 'completed'
  const canSetWinner =
    selectedMonth != null && selectedMonth > 1 && selectedMonthStatus != null && !selectedHasWinner

  function submitManualWinner() {
    if (!selectedMonth || !manualSeatId) {
      setError('Select a member.')
      return
    }
    const seat = eligibleSeats.find((s) => String(s.groupMemberId) === manualSeatId)
    if (!seat) {
      setError('Selected member is not eligible (already won or inactive).')
      return
    }

    if (manualMode === 'random') {
      if (
        !confirm(
          `Set #${seat.memberNumber} ${seat.memberName} as Month ${selectedMonth} winner using chart Random amount (${formatInr(selectedMonthMeta?.randomAmount ?? 0)})?`,
        )
      ) {
        return
      }
      approveMutation.mutate({
        monthNumber: selectedMonth,
        winnerMemberId: seat.memberId,
        winnerGroupMemberId: seat.groupMemberId,
        winningBidAmount: 0,
        useRandomAmount: true,
      })
      return
    }

    const amount = Number(manualBoli || selectedMonthMeta?.nextBoliAmount || selectedMonthMeta?.boliStartAmount)
    if (!Number.isFinite(amount) || amount <= 0) {
      setError('Enter the boli (receive) amount.')
      return
    }
    if (
      !confirm(
        `Set #${seat.memberNumber} ${seat.memberName}${seat.handLabel ? ` · ${seat.handLabel}` : ''} as Month ${selectedMonth} winner with boli ${formatInr(amount)}?`,
      )
    ) {
      return
    }
    approveMutation.mutate({
      monthNumber: selectedMonth,
      winnerMemberId: seat.memberId,
      winnerGroupMemberId: seat.groupMemberId,
      winningBidAmount: 0,
      boliAmount: amount,
    })
  }

  return (
    <div>
      <Button asChild variant="ghost" className="mb-4 px-0 text-muted-foreground">
        <Link to={`/admin/groups/${id}`}>
          <ArrowLeft className="h-4 w-4" />
          Back to ledger
        </Link>
      </Button>

      <PageHeader
        title={data ? `${t('pages.biddingTitle')} · ${data.groupName}` : t('pages.biddingTitle')}
        description={
          data
            ? `Collection ${formatInr(data.totalMonthlyCollection)} · step ${formatInr(data.boliStepAmount ?? 1000)} · contribution ${formatInr(data.monthlyContribution)}`
            : t('pages.biddingDesc')
        }
        actions={
          <div className="flex gap-2">
            <Button asChild size="sm" variant="outline">
              <Link to={`/admin/groups/${id}/bc-chart`}>BC Chart</Link>
            </Button>
            <Badge>Admin</Badge>
          </div>
        }
      />

      {message ? <p className="mb-3 text-sm text-emerald-700">{message}</p> : null}
      {error ? <p className="mb-3 text-sm text-destructive">{error}</p> : null}
      {isLoading ? <p className="text-sm text-muted-foreground">Loading bidding status…</p> : null}

      {data ? (
        <>
          {lastSettledMonth === 0 && !data.month1Allocated && !month1?.winnerMemberId ? (
            <Card className="mb-4 border-teal-200 bg-teal-50/40">
              <CardHeader className="pb-2">
                <CardTitle className="text-base">Month 1 · Organiser pot</CardTitle>
                <CardDescription>
                  First month collection goes to the organiser (no bid). Organiser:{' '}
                  <strong>{data.organiserName ?? 'not set — edit the group first'}</strong>
                </CardDescription>
              </CardHeader>
              <CardContent className="flex flex-wrap items-center gap-2">
                <Button
                  disabled={!data.organiserMemberId || allocateOrganiserMutation.isPending}
                  onClick={() => {
                    if (
                      confirm(
                        `Assign Month 1 pot (${formatInr(data.totalMonthlyCollection)}) to ${data.organiserName}? This records it on the ledger even if members already paid.`,
                      )
                    ) {
                      allocateOrganiserMutation.mutate()
                    }
                  }}
                >
                  {allocateOrganiserMutation.isPending ? 'Assigning…' : 'Assign Month 1 to organiser'}
                </Button>
                {!data.organiserMemberId ? (
                  <Button asChild size="sm" variant="outline">
                    <Link to="/admin/groups">Set organiser on Edit group</Link>
                  </Button>
                ) : null}
              </CardContent>
            </Card>
          ) : null}

          {settledMonthsSummary.length > 0 ? (
            <p className="mb-4 text-sm text-muted-foreground">
              Settled (hidden): {settledMonthsSummary.map((m) => `M${m.monthNumber}`).join(', ')} — paid +
              winner assigned. Months paid without a winner stay visible.
            </p>
          ) : null}
        </>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-5">
        <Card className="lg:col-span-3">
          <CardHeader>
            <CardTitle>Months</CardTitle>
            <CardDescription>
              Months are hidden only after payments are complete <em>and</em> a winner has received the pot.
              If paid but no winner yet, use View / set winner.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {!data ? null : visibleMonths.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                All months are settled (payments complete and winner assigned).
              </p>
            ) : (
              visibleMonths.map((m) => (
                <div key={m.monthNumber} className="rounded-xl border border-border p-4">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <div className="flex items-center gap-2">
                        <p className="font-semibold">Month {m.monthNumber}</p>
                        <Badge variant={statusVariant(m.biddingStatus)}>
                          {m.biddingStatus.replace('_', ' ')}
                        </Badge>
                        {m.monthNumber === 1 ? <Badge variant="muted">Organiser</Badge> : null}
                        {m.paymentDone && !m.winnerMemberId && !m.winnerGroupMemberId ? (
                          <Badge variant="warning">Paid · needs winner</Badge>
                        ) : null}
                      </div>
                      <p className="mt-1 text-sm text-muted-foreground">
                        {m.monthNumber === 1
                          ? m.winnerMemberName
                            ? `Taken by ${m.winnerMemberName} (organiser)`
                            : `Reserved for organiser${data.organiserName ? ` (${data.organiserName})` : ''}`
                          : `${m.totalBids} bid(s)${
                              m.boliStartAmount != null ? ` · boli start ${formatInr(m.boliStartAmount)}` : ''
                            }${m.randomAmount != null ? ` · random ${formatInr(m.randomAmount)}` : ''}${
                              m.nextBoliAmount != null ? ` · next ${formatInr(m.nextBoliAmount)}` : ''
                            }${m.winnerMemberName ? ` · winner ${m.winnerMemberName}` : ''}${
                              m.paymentDone && !m.winnerMemberName ? ' · payments complete' : ''
                            }`}
                      </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      {m.monthNumber > 1 ? (
                        <Button size="sm" variant="outline" onClick={() => setSelectedMonth(m.monthNumber)}>
                          View / set winner
                        </Button>
                      ) : null}
                      {m.monthNumber > 1 &&
                      (m.biddingStatus === 'not_started' || m.biddingStatus === 'closed') &&
                      !m.winnerMemberId ? (
                        <Button
                          size="sm"
                          onClick={() =>
                            setOpenForm({
                              month: m.monthNumber,
                              endDate: new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10),
                            })
                          }
                        >
                          Open
                        </Button>
                      ) : null}
                      {m.monthNumber > 1 && m.biddingStatus === 'open' ? (
                        <Button size="sm" variant="secondary" onClick={() => closeMutation.mutate(m.monthNumber)}>
                          Close
                        </Button>
                      ) : null}
                    </div>
                  </div>

                  {openForm?.month === m.monthNumber ? (
                    <div className="mt-4 grid gap-3 rounded-lg bg-muted/50 p-3 sm:grid-cols-3">
                      <div className="space-y-1 sm:col-span-2">
                        <Label>End date</Label>
                        <Input
                          type="date"
                          value={openForm.endDate}
                          onChange={(e) => setOpenForm({ ...openForm, endDate: e.target.value })}
                        />
                        <p className="text-xs text-muted-foreground">
                          Boli start from BC Chart:{' '}
                          {m.boliStartAmount != null ? formatInr(m.boliStartAmount) : 'not set — edit BC Chart first'}
                          {data.boliStepAmount != null ? ` · step ${formatInr(data.boliStepAmount)}` : ''}
                        </p>
                      </div>
                      <div className="flex items-end gap-2">
                        <Button
                          onClick={() =>
                            openMutation.mutate({
                              monthNumber: openForm.month,
                              endDate: openForm.endDate,
                            })
                          }
                        >
                          Confirm open
                        </Button>
                        <Button variant="ghost" onClick={() => setOpenForm(null)}>
                          Cancel
                        </Button>
                      </div>
                    </div>
                  ) : null}
                </div>
              ))
            )}
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Gavel className="h-4 w-4" />
              {selectedMonth ? `Month ${selectedMonth} winner` : 'Winner'}
            </CardTitle>
            <CardDescription>
              Approve app bids, or set winner manually. Amounts come from BC Chart.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {!selectedMonth ? (
              <p className="text-sm text-muted-foreground">Select a month (2+) to review bids or set a winner.</p>
            ) : null}

            {selectedMonth && selectedMonthMeta ? (
              <p className="rounded-lg bg-muted/60 px-3 py-2 text-xs text-muted-foreground">
                Chart: random {formatInr(selectedMonthMeta.randomAmount ?? 0)}
                {selectedMonthMeta.boliStartAmount != null
                  ? ` · boli start ${formatInr(selectedMonthMeta.boliStartAmount)}`
                  : ' · no boli'}
                {selectedMonthMeta.nextBoliAmount != null
                  ? ` · next allowed ${formatInr(selectedMonthMeta.nextBoliAmount)}`
                  : ''}
                {data?.boliStepAmount != null ? ` · step ${formatInr(data.boliStepAmount)}` : ''}
              </p>
            ) : null}

            {canSetWinner ? (
              <div className="space-y-3 rounded-xl border border-dashed border-teal-300 bg-teal-50/50 p-3">
                <p className="text-sm font-medium text-teal-900">Set winner manually</p>
                <div className="flex flex-wrap gap-2">
                  <Button
                    size="sm"
                    variant={manualMode === 'boli' ? 'default' : 'outline'}
                    onClick={() => setManualMode('boli')}
                  >
                    With boli
                  </Button>
                  <Button
                    size="sm"
                    variant={manualMode === 'random' ? 'default' : 'outline'}
                    onClick={() => setManualMode('random')}
                  >
                    Random (no boli)
                  </Button>
                </div>
                <div className="space-y-1">
                  <Label htmlFor="manual-seat">Member / seat</Label>
                  <select
                    id="manual-seat"
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    value={manualSeatId}
                    onChange={(e) => setManualSeatId(e.target.value)}
                  >
                    <option value="">Select member…</option>
                    {eligibleSeats.map((s) => (
                      <option key={s.groupMemberId} value={s.groupMemberId}>
                        #{s.memberNumber} {s.memberName}
                        {s.handLabel ? ` · ${s.handLabel}` : ''}
                      </option>
                    ))}
                  </select>
                </div>
                {manualMode === 'boli' ? (
                  <div className="space-y-1">
                    <Label htmlFor="manual-boli">Boli receive amount (₹)</Label>
                    <Input
                      id="manual-boli"
                      inputMode="decimal"
                      placeholder={
                        selectedMonthMeta?.nextBoliAmount != null
                          ? String(selectedMonthMeta.nextBoliAmount)
                          : 'e.g. 85000'
                      }
                      value={manualBoli}
                      onChange={(e) => setManualBoli(e.target.value)}
                    />
                  </div>
                ) : (
                  <p className="text-xs text-muted-foreground">
                    Uses chart random amount {formatInr(selectedMonthMeta?.randomAmount ?? 0)} (spin / no bid).
                  </p>
                )}
                <Button
                  className="w-full"
                  disabled={approveMutation.isPending || eligibleSeats.length === 0}
                  onClick={submitManualWinner}
                >
                  {approveMutation.isPending ? 'Saving…' : 'Confirm manual winner'}
                </Button>
                {eligibleSeats.length === 0 ? (
                  <p className="text-xs text-muted-foreground">No eligible seats left (all have already won).</p>
                ) : null}
              </div>
            ) : null}

            {selectedMonth && selectedHasWinner ? (
              <p className="text-sm text-muted-foreground">This month already has an approved winner.</p>
            ) : null}

            <div className="space-y-2">
              <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">App bids</p>
              {selectedMonth && (!bids || bids.length === 0) ? (
                <p className="text-sm text-muted-foreground">No in-app bids yet for this month.</p>
              ) : null}
              {bids?.map((b) => (
                <div key={b.id} className="rounded-lg border border-border p-3">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <p className="font-medium">
                        #{b.memberNumber} {b.memberName}
                        {b.handLabel ? ` · ${b.handLabel}` : ''}
                      </p>
                      <p className="text-sm text-muted-foreground">
                        Boli {formatInr(b.boliAmount ?? 0)} · {b.bidStatus}
                      </p>
                    </div>
                    {canSetWinner ? (
                      <Button
                        size="sm"
                        onClick={() =>
                          approveMutation.mutate({
                            monthNumber: selectedMonth!,
                            winnerMemberId: b.memberId,
                            winnerGroupMemberId: b.groupMemberId,
                            winningBidAmount: b.bidAmount,
                          })
                        }
                      >
                        Approve
                      </Button>
                    ) : null}
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
