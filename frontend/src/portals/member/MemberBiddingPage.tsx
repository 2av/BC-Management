import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { formatInr, type MemberDashboard } from '@/features/groups/types'
import type { BidItem, GroupBiddingOverview } from '@/features/bidding/types'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function MemberBiddingPage() {
  const { t } = useTranslation()
  const api = useApi()
  const qc = useQueryClient()
  const [groupId, setGroupId] = useState<number | null>(null)
  const [groupMemberId, setGroupMemberId] = useState<number | null>(null)
  const [bidAmount, setBidAmount] = useState('')
  const [monthNumber, setMonthNumber] = useState<number | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const { data: dash } = useQuery({
    queryKey: ['member-dashboard'],
    queryFn: () => api.get<MemberDashboard>('/api/members/me/dashboard'),
  })

  const groupOptions = useMemo(() => {
    const map = new Map<number, { groupId: number; groupName: string }>()
    for (const g of dash?.groups ?? []) {
      if (!map.has(g.groupId)) map.set(g.groupId, { groupId: g.groupId, groupName: g.groupName })
    }
    return [...map.values()]
  }, [dash])

  const activeGroupId = groupId ?? groupOptions[0]?.groupId ?? null
  const seatsInGroup = useMemo(
    () => (dash?.groups ?? []).filter((g) => g.groupId === activeGroupId),
    [dash, activeGroupId],
  )
  const activeSeatId = groupMemberId ?? seatsInGroup[0]?.groupMemberId ?? null

  const { data: bidding } = useQuery({
    queryKey: ['bidding', activeGroupId],
    queryFn: () => api.get<GroupBiddingOverview>(`/api/groups/${activeGroupId}/bidding`),
    enabled: !!activeGroupId,
  })

  const openMonths = useMemo(
    () => bidding?.months.filter((m) => m.biddingStatus === 'open') ?? [],
    [bidding],
  )

  const selectedMonth = monthNumber ?? openMonths[0]?.monthNumber ?? null

  const { data: bids } = useQuery({
    queryKey: ['month-bids', activeGroupId, selectedMonth],
    queryFn: () => api.get<BidItem[]>(`/api/groups/${activeGroupId}/bidding/months/${selectedMonth}/bids`),
    enabled: !!activeGroupId && selectedMonth != null,
  })

  const placeBid = useMutation({
    mutationFn: () =>
      api.post(`/api/groups/${activeGroupId}/bidding/bids`, {
        monthNumber: selectedMonth,
        bidAmount: Number(bidAmount),
        groupMemberId: activeSeatId,
      }),
    onSuccess: async () => {
      setMessage('Bid placed successfully.')
      setError(null)
      setBidAmount('')
      await qc.invalidateQueries({ queryKey: ['month-bids', activeGroupId, selectedMonth] })
      await qc.invalidateQueries({ queryKey: ['bidding', activeGroupId] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const selectedMeta = openMonths.find((m) => m.monthNumber === selectedMonth)

  return (
    <div>
      <PageHeader
        title={t('pages.memberBiddingTitle')}
        description={t('pages.memberBiddingDesc')}
      />

      {message ? <p className="mb-3 text-sm text-emerald-700">{message}</p> : null}
      {error ? <p className="mb-3 text-sm text-destructive">{error}</p> : null}

      <div className="mb-4 flex flex-wrap gap-2">
        {groupOptions.map((g) => (
          <Button
            key={g.groupId}
            size="sm"
            variant={activeGroupId === g.groupId ? 'default' : 'outline'}
            onClick={() => {
              setGroupId(g.groupId)
              setGroupMemberId(null)
              setMonthNumber(null)
            }}
          >
            {g.groupName}
          </Button>
        ))}
      </div>

      {!activeGroupId ? (
        <p className="text-sm text-muted-foreground">Join a group to participate in bidding.</p>
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>{bidding?.groupName ?? 'Group'}</CardTitle>
              <CardDescription>
                Collection {bidding ? formatInr(bidding.totalMonthlyCollection) : '—'}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {seatsInGroup.length > 1 ? (
                <div className="space-y-2">
                  <Label>Your hand (seat)</Label>
                  <div className="flex flex-wrap gap-2">
                    {seatsInGroup.map((s) => (
                      <Button
                        key={s.groupMemberId}
                        size="sm"
                        variant={activeSeatId === s.groupMemberId ? 'default' : 'outline'}
                        onClick={() => setGroupMemberId(s.groupMemberId)}
                      >
                        #{s.memberNumber}
                        {s.handLabel ? ` · ${s.handLabel}` : ''}
                      </Button>
                    ))}
                  </div>
                </div>
              ) : seatsInGroup[0] ? (
                <p className="text-sm text-muted-foreground">
                  Seat #{seatsInGroup[0].memberNumber}
                  {seatsInGroup[0].handLabel ? ` · ${seatsInGroup[0].handLabel}` : ''}
                </p>
              ) : null}

              {openMonths.length === 0 ? (
                <p className="text-sm text-muted-foreground">No months are open for bidding right now.</p>
              ) : (
                <>
                  <div className="space-y-2">
                    <Label>Open month</Label>
                    <div className="flex flex-wrap gap-2">
                      {openMonths.map((m) => (
                        <Button
                          key={m.monthNumber}
                          size="sm"
                          variant={selectedMonth === m.monthNumber ? 'default' : 'outline'}
                          onClick={() => setMonthNumber(m.monthNumber)}
                        >
                          Month {m.monthNumber}
                        </Button>
                      ))}
                    </div>
                  </div>
                  {selectedMeta ? (
                    <p className="text-sm text-muted-foreground">
                      Range {formatInr(selectedMeta.minimumBidAmount)} – {formatInr(selectedMeta.maximumBidAmount)}
                      {selectedMeta.biddingEndDate
                        ? ` · ends ${new Date(selectedMeta.biddingEndDate).toLocaleDateString('en-IN')}`
                        : ''}
                    </p>
                  ) : null}
                  <div className="space-y-2">
                    <Label htmlFor="bid">Your bid amount</Label>
                    <Input
                      id="bid"
                      type="number"
                      min={0}
                      value={bidAmount}
                      onChange={(e) => setBidAmount(e.target.value)}
                      placeholder="Enter bid"
                    />
                  </div>
                  <Button
                    disabled={!selectedMonth || !bidAmount || !activeSeatId || placeBid.isPending}
                    onClick={() => placeBid.mutate()}
                  >
                    Submit bid
                  </Button>
                </>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Current bids</CardTitle>
              <CardDescription>
                {selectedMonth ? `Month ${selectedMonth}` : 'Select an open month'}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-2">
              {(bids ?? []).map((b) => (
                <div key={b.id} className="rounded-lg border border-border px-3 py-2 text-sm">
                  #{b.memberNumber} {b.memberName}
                  {b.handLabel ? ` · ${b.handLabel}` : ''} — {formatInr(b.bidAmount)} ({b.bidStatus})
                </div>
              ))}
              {selectedMonth && (bids?.length ?? 0) === 0 ? (
                <p className="text-sm text-muted-foreground">No bids yet.</p>
              ) : null}
              <Button asChild variant="outline" size="sm" className="mt-2">
                <Link to={activeGroupId ? `/member/groups/${activeGroupId}` : '/member'}>View ledger</Link>
              </Button>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  )
}
