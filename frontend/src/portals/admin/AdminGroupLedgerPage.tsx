import { useQuery } from '@tanstack/react-query'
import { Link, useLocation, useParams } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { useApi } from '@/shared/api/client'
import { formatInr, type GroupLedger } from '@/features/groups/types'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export function AdminGroupLedgerPage() {
  const { id } = useParams()
  const location = useLocation()
  const api = useApi()
  const groupId = Number(id)
  const isAdmin = location.pathname.startsWith('/admin')
  const { data, isLoading, error } = useQuery({
    queryKey: ['group-ledger', groupId],
    queryFn: () => api.get<GroupLedger>(`/api/groups/${groupId}/ledger`),
    enabled: Number.isFinite(groupId) && groupId > 0,
  })

  const months = data?.monthlyBids.map((b) => b.monthNumber) ?? []

  return (
    <div>
      {isAdmin ? (
        <Button asChild variant="ghost" className="mb-4 px-0 text-muted-foreground hover:text-foreground">
          <Link to="/admin/groups">
            <ArrowLeft className="h-4 w-4" />
            Back to groups
          </Link>
        </Button>
      ) : null}

      {isLoading ? <p className="text-sm text-muted-foreground">Loading ledger…</p> : null}
      {error ? <p className="text-sm text-destructive">{(error as Error).message}</p> : null}

      {data ? (
        <>
          <PageHeader
            title={data.groupName}
            description={`${data.totalMembers} members · ${formatInr(data.monthlyContribution)} / month · collection ${formatInr(data.totalMonthlyCollection)}${
              data.organiserName ? ` · organiser ${data.organiserName}` : ''
            }`}
            actions={
              <div className="flex items-center gap-2">
                <Badge variant={data.status === 'active' ? 'success' : 'muted'}>{data.status}</Badge>
                {isAdmin ? (
                  <>
                    <Button asChild size="sm">
                      <Link to={`/admin/groups/${groupId}/bidding`}>Manage bidding</Link>
                    </Button>
                    <Button asChild size="sm" variant="secondary">
                      <Link to={`/admin/groups/${groupId}/random-picks`}>Random pick</Link>
                    </Button>
                  </>
                ) : (
                  <>
                    <Button asChild size="sm" variant="outline">
                      <Link to="/member/bidding">Place bid</Link>
                    </Button>
                    <Button asChild size="sm" variant="secondary">
                      <Link to={`/member/groups/${groupId}/random-picks`}>Random pick</Link>
                    </Button>
                    <Button asChild size="sm" variant="outline">
                      <Link to={`/member/groups/${groupId}/invoice`}>Invoice</Link>
                    </Button>
                  </>
                )}
              </div>
            }
          />

          {!data.month1Allocated ? (
            <p className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
              Month 1 pot is not on the ledger yet
              {data.organiserName ? ` (organiser: ${data.organiserName})` : ' — set organiser on Edit group first'}.
              {isAdmin ? (
                <>
                  {' '}
                  Go to{' '}
                  <Link className="font-medium underline" to={`/admin/groups/${groupId}/bidding`}>
                    Manage bidding
                  </Link>{' '}
                  → <strong>Assign Month 1 to organiser</strong>.
                </>
              ) : null}
            </p>
          ) : null}
          <Card className="mb-6">
            <CardHeader>
              <CardTitle>Deposit / Bid details</CardTitle>
              <CardDescription>Month-wise winner, bid amount, net payable, and gain per member.</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="overflow-x-auto rounded-lg border border-border">
                <table className="w-full min-w-[800px] text-left text-sm">
                  <thead className="bg-navy text-white">
                    <tr>
                      <th className="px-3 py-2.5 font-medium">Month</th>
                      <th className="px-3 py-2.5 font-medium">Taken by</th>
                      <th className="px-3 py-2.5 font-medium">Bid?</th>
                      <th className="px-3 py-2.5 font-medium">Bid amount</th>
                      <th className="px-3 py-2.5 font-medium">Net payable</th>
                      <th className="px-3 py-2.5 font-medium">Gain / member</th>
                      <th className="px-3 py-2.5 font-medium">Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.monthlyBids.map((b) => (
                      <tr key={b.monthNumber} className="border-t border-border odd:bg-card even:bg-muted/30">
                        <td className="px-3 py-2 font-medium">{b.monthNumber}</td>
                        <td className="px-3 py-2">{b.takenByMemberName ?? '—'}</td>
                        <td className="px-3 py-2">{b.isBid ? 'Yes' : 'No'}</td>
                        <td className="px-3 py-2">{formatInr(b.bidAmount)}</td>
                        <td className="px-3 py-2">{formatInr(b.netPayable)}</td>
                        <td className="px-3 py-2">{formatInr(b.gainPerMember)}</td>
                        <td className="px-3 py-2">
                          {b.paymentDate ? new Date(b.paymentDate).toLocaleDateString('en-IN') : '—'}
                        </td>
                      </tr>
                    ))}
                    {data.monthlyBids.length === 0 ? (
                      <tr>
                        <td colSpan={7} className="px-3 py-8 text-center text-muted-foreground">
                          No monthly bids recorded yet.
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Transaction details</CardTitle>
              <CardDescription>Member-wise payments across months with totals and profit.</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="overflow-x-auto rounded-lg border border-border">
                <table className="w-full min-w-[900px] text-left text-sm">
                  <thead className="bg-primary text-primary-foreground">
                    <tr>
                      <th className="sticky left-0 bg-primary px-3 py-2.5 font-medium">#</th>
                      <th className="sticky left-8 bg-primary px-3 py-2.5 font-medium">Member</th>
                      {months.map((m) => (
                        <th key={m} className="px-3 py-2.5 font-medium">
                          M{m}
                        </th>
                      ))}
                      <th className="px-3 py-2.5 font-medium">Total paid</th>
                      <th className="px-3 py-2.5 font-medium">Given</th>
                      <th className="px-3 py-2.5 font-medium">Profit</th>
                      <th className="px-3 py-2.5 font-medium">Invoice</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.members.map((row) => (
                      <tr key={row.groupMemberId} className="border-t border-border odd:bg-card even:bg-muted/20">
                        <td className="sticky left-0 bg-inherit px-3 py-2">{row.memberNumber}</td>
                        <td className="sticky left-8 bg-inherit px-3 py-2 font-medium">{row.memberName}</td>
                        {months.map((m) => {
                          const amount = row.paymentsByMonth[String(m)] ?? row.paymentsByMonth[m as unknown as string]
                          return (
                            <td key={m} className="px-3 py-2 tabular-nums">
                              {amount != null ? formatInr(amount) : '—'}
                            </td>
                          )
                        })}
                        <td className="px-3 py-2 font-medium tabular-nums">{formatInr(row.totalPaid)}</td>
                        <td className="px-3 py-2 tabular-nums">{formatInr(row.givenAmount)}</td>
                        <td
                          className={`px-3 py-2 font-medium tabular-nums ${row.profit >= 0 ? 'text-emerald-700' : 'text-destructive'}`}
                        >
                          {formatInr(row.profit)}
                        </td>
                        <td className="px-3 py-2">
                          <Button asChild size="sm" variant="outline">
                            <Link
                              to={
                                isAdmin
                                  ? `/admin/groups/${groupId}/members/${row.memberId}/invoice`
                                  : `/member/groups/${groupId}/invoice`
                              }
                            >
                              Invoice
                            </Link>
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
