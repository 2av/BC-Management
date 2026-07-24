import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { useMemo, useState } from 'react'
import { ArrowLeft, Gavel } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { formatInr } from '@/features/groups/types'
import type { BidItem, GroupBiddingOverview } from '@/features/bidding/types'
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
  const [openForm, setOpenForm] = useState<{ month: number; endDate: string; min: string; max: string } | null>(null)
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

  const openMutation = useMutation({
    mutationFn: (body: { monthNumber: number; endDate: string; minBidAmount: number; maxBidAmount: number }) =>
      api.post(`/api/groups/${id}/bidding/open`, body),
    onSuccess: async () => {
      setMessage('Bidding opened.')
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
    }) => api.post(`/api/groups/${id}/bidding/approve-winner`, body),
    onSuccess: async () => {
      setMessage('Winner approved. Ledger updated.')
      setError(null)
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

  const defaultMax = useMemo(() => (data ? Math.max(0, data.totalMonthlyCollection - 1) : 0), [data])
  const month1 = data?.months.find((m) => m.monthNumber === 1)

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
            ? `Collection ${formatInr(data.totalMonthlyCollection)} · contribution ${formatInr(data.monthlyContribution)}`
            : t('pages.biddingDesc')
        }
        actions={<Badge>Admin</Badge>}
      />

      {message ? <p className="mb-3 text-sm text-emerald-700">{message}</p> : null}
      {error ? <p className="mb-3 text-sm text-destructive">{error}</p> : null}
      {isLoading ? <p className="text-sm text-muted-foreground">Loading bidding status…</p> : null}

      {data ? (
        <Card className="mb-4 border-teal-200 bg-teal-50/40">
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Month 1 · Organiser pot</CardTitle>
            <CardDescription>
              First month collection goes to the organiser (no bid). Organiser:{' '}
              <strong>{data.organiserName ?? 'not set — edit the group first'}</strong>
              {data.month1Allocated || month1?.winnerMemberName
                ? ` · currently shown as ${month1?.winnerMemberName ?? 'allocated'}`
                : null}
            </CardDescription>
          </CardHeader>
          <CardContent className="flex flex-wrap items-center gap-2">
            {!data.month1Allocated && !month1?.winnerMemberId ? (
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
            ) : (
              <Badge variant="success">Month 1 allocated</Badge>
            )}
            {!data.organiserMemberId ? (
              <Button asChild size="sm" variant="outline">
                <Link to="/admin/groups">Set organiser on Edit group</Link>
              </Button>
            ) : null}
          </CardContent>
        </Card>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-5">
        <Card className="lg:col-span-3">
          <CardHeader>
            <CardTitle>Months</CardTitle>
            <CardDescription>Month 1 uses organiser allocation. From Month 2, open bidding then approve a winner.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {data?.months.map((m) => (
              <div key={m.monthNumber} className="rounded-xl border border-border p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <div className="flex items-center gap-2">
                      <p className="font-semibold">Month {m.monthNumber}</p>
                      <Badge variant={statusVariant(m.biddingStatus)}>{m.biddingStatus.replace('_', ' ')}</Badge>
                      {m.monthNumber === 1 ? <Badge variant="muted">Organiser</Badge> : null}
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                      {m.monthNumber === 1
                        ? m.winnerMemberName
                          ? `Taken by ${m.winnerMemberName} (organiser)`
                          : `Reserved for organiser${data.organiserName ? ` (${data.organiserName})` : ''}`
                        : `${m.totalBids} bid(s)${
                            m.minimumBidAmount || m.maximumBidAmount
                              ? ` · range ${formatInr(m.minimumBidAmount)} – ${formatInr(m.maximumBidAmount)}`
                              : ''
                          }${m.winnerMemberName ? ` · winner ${m.winnerMemberName}` : ''}`}
                    </p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {m.monthNumber > 1 ? (
                      <Button size="sm" variant="outline" onClick={() => setSelectedMonth(m.monthNumber)}>
                        View bids
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
                            min: '0',
                            max: String(defaultMax),
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
                  <div className="mt-4 grid gap-3 rounded-lg bg-muted/50 p-3 sm:grid-cols-4">
                    <div className="space-y-1">
                      <Label>End date</Label>
                      <Input
                        type="date"
                        value={openForm.endDate}
                        onChange={(e) => setOpenForm({ ...openForm, endDate: e.target.value })}
                      />
                    </div>
                    <div className="space-y-1">
                      <Label>Min bid</Label>
                      <Input value={openForm.min} onChange={(e) => setOpenForm({ ...openForm, min: e.target.value })} />
                    </div>
                    <div className="space-y-1">
                      <Label>Max bid</Label>
                      <Input value={openForm.max} onChange={(e) => setOpenForm({ ...openForm, max: e.target.value })} />
                    </div>
                    <div className="flex items-end gap-2">
                      <Button
                        onClick={() =>
                          openMutation.mutate({
                            monthNumber: openForm.month,
                            endDate: openForm.endDate,
                            minBidAmount: Number(openForm.min),
                            maxBidAmount: Number(openForm.max),
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
            ))}
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Gavel className="h-4 w-4" />
              {selectedMonth ? `Month ${selectedMonth} bids` : 'Bids'}
            </CardTitle>
            <CardDescription>Lowest bid is typically preferred; admin chooses the winner.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {!selectedMonth ? <p className="text-sm text-muted-foreground">Select a month (2+) to review bids.</p> : null}
            {selectedMonth && (!bids || bids.length === 0) ? (
              <p className="text-sm text-muted-foreground">No bids yet for this month.</p>
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
                      {formatInr(b.bidAmount)} · {b.bidStatus}
                    </p>
                  </div>
                  {data?.months.find((m) => m.monthNumber === selectedMonth)?.biddingStatus !== 'completed' ? (
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
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
