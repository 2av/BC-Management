import { useQuery } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { Download } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { formatInr, type GroupListItem } from '@/features/groups/types'
import { PageHeader } from '@/components/layout/PageHeader'
import { StatCard } from '@/components/layout/StatCard'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'

type Overview = {
  totalGroups: number
  totalMembers: number
  totalCollected: number
  totalPending: number
  groups: {
    groupId: number
    groupName: string
    totalMembers: number
    monthlyContribution: number
    totalCollected: number
    paidCount: number
    pendingCount: number
    pendingAmount: number
    bidCount: number
    totalDistributed: number
  }[]
}

type PaymentRow = {
  groupName: string
  memberName: string
  memberNumber: number
  monthNumber: number
  paymentAmount: number
  paymentStatus: string
  paymentDate: string | null
  winnerName: string | null
}

type BidRow = {
  groupName: string
  monthNumber: number
  winnerName: string | null
  bidAmount: number
  netPayable: number
  gainPerMember: number
  paymentDate: string | null
}

export function AdminReportsPage() {
  const { t } = useTranslation()
  const api = useApi()
  const [tab, setTab] = useState<'overview' | 'payments' | 'bids'>('overview')
  const [groupId, setGroupId] = useState('all')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')

  const { data: groups } = useQuery({
    queryKey: ['groups'],
    queryFn: () => api.get<GroupListItem[]>('/api/groups'),
  })

  const { data: overview } = useQuery({
    queryKey: ['report-overview'],
    queryFn: () => api.get<Overview>('/api/reports/overview'),
  })

  const paymentsPath = useMemo(() => {
    const params = new URLSearchParams()
    if (groupId !== 'all') params.set('groupId', groupId)
    if (from) params.set('from', from)
    if (to) params.set('to', to)
    const q = params.toString()
    return `/api/reports/payments${q ? `?${q}` : ''}`
  }, [groupId, from, to])

  const { data: payments } = useQuery({
    queryKey: ['report-payments', groupId, from, to],
    queryFn: () => api.get<PaymentRow[]>(paymentsPath),
    enabled: tab === 'payments',
  })

  const bidsPath = groupId === 'all' ? '/api/reports/bids' : `/api/reports/bids?groupId=${groupId}`
  const { data: bids } = useQuery({
    queryKey: ['report-bids', groupId],
    queryFn: () => api.get<BidRow[]>(bidsPath),
    enabled: tab === 'bids',
  })

  async function download(type: string) {
    const params = new URLSearchParams()
    if (groupId !== 'all') params.set('groupId', groupId)
    if (from) params.set('from', from)
    if (to) params.set('to', to)
    const q = params.toString()
    const path = `/api/reports/export/${type}${q ? `?${q}` : ''}`
    const token = JSON.parse(localStorage.getItem('mitra_auth') || '{}')?.accessToken
    const res = await fetch(`${import.meta.env.VITE_API_URL ?? 'http://localhost:5027'}${path}`, {
      headers: token ? { Authorization: `Bearer ${token}` } : {},
    })
    if (!res.ok) throw new Error('Export failed')
    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${type}_export.csv`
    a.click()
    URL.revokeObjectURL(url)
  }

  return (
    <div>
      <PageHeader
        title={t('pages.reportsTitle')}
        description={t('pages.reportsDesc')}
        actions={
          <div className="flex flex-wrap gap-2">
            <Button size="sm" variant="outline" onClick={() => download('groups')}>
              <Download className="h-4 w-4" /> Groups CSV
            </Button>
            <Button size="sm" variant="outline" onClick={() => download('members')}>
              <Download className="h-4 w-4" /> Members CSV
            </Button>
            <Button size="sm" variant="outline" onClick={() => download('payments')}>
              <Download className="h-4 w-4" /> Payments CSV
            </Button>
            <Button size="sm" variant="outline" onClick={() => download('bids')}>
              <Download className="h-4 w-4" /> Bids CSV
            </Button>
          </div>
        }
      />

      <div className="mb-4 flex flex-wrap gap-2">
        {(['overview', 'payments', 'bids'] as const).map((t) => (
          <Button key={t} size="sm" variant={tab === t ? 'default' : 'outline'} onClick={() => setTab(t)}>
            {t[0].toUpperCase() + t.slice(1)}
          </Button>
        ))}
      </div>

      <div className="mb-4 grid gap-3 rounded-xl border border-border bg-muted/40 p-3 sm:grid-cols-3">
        <div className="space-y-1.5">
          <Label>Group</Label>
          <select
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
            value={groupId}
            onChange={(e) => setGroupId(e.target.value)}
          >
            <option value="all">All groups</option>
            {groups?.map((g) => (
              <option key={g.id} value={g.id}>
                {g.groupName}
              </option>
            ))}
          </select>
        </div>
        <div className="space-y-1.5">
          <Label>From</Label>
          <input
            type="date"
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
            value={from}
            onChange={(e) => setFrom(e.target.value)}
          />
        </div>
        <div className="space-y-1.5">
          <Label>To</Label>
          <input
            type="date"
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
            value={to}
            onChange={(e) => setTo(e.target.value)}
          />
        </div>
      </div>

      {tab === 'overview' && overview ? (
        <>
          <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Groups" value={`${overview.totalGroups}`} />
            <StatCard label="Members" value={`${overview.totalMembers}`} />
            <StatCard label="Collected" value={formatInr(overview.totalCollected)} />
            <StatCard label="Pending" value={formatInr(overview.totalPending)} />
          </div>
          <Card>
            <CardHeader>
              <CardTitle>By group</CardTitle>
            </CardHeader>
            <CardContent className="overflow-x-auto">
              <table className="w-full min-w-[800px] text-left text-sm">
                <thead>
                  <tr className="border-b border-border bg-muted/60">
                    <th className="px-3 py-2.5 font-semibold">Group</th>
                    <th className="px-3 py-2.5 font-semibold">Collected</th>
                    <th className="px-3 py-2.5 font-semibold">Pending</th>
                    <th className="px-3 py-2.5 font-semibold">Bids</th>
                    <th className="px-3 py-2.5 font-semibold">Distributed</th>
                  </tr>
                </thead>
                <tbody>
                  {overview.groups.map((g) => (
                    <tr key={g.groupId} className="border-b border-border">
                      <td className="px-3 py-2 font-medium">{g.groupName}</td>
                      <td className="px-3 py-2">{formatInr(g.totalCollected)}</td>
                      <td className="px-3 py-2">{formatInr(g.pendingAmount)}</td>
                      <td className="px-3 py-2">{g.bidCount}</td>
                      <td className="px-3 py-2">{formatInr(g.totalDistributed)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </CardContent>
          </Card>
        </>
      ) : null}

      {tab === 'payments' ? (
        <Card>
          <CardHeader>
            <CardTitle>Payments</CardTitle>
            <CardDescription>{payments?.length ?? 0} rows</CardDescription>
          </CardHeader>
          <CardContent className="overflow-x-auto">
            <table className="w-full min-w-[900px] text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-muted/60">
                  <th className="px-3 py-2.5 font-semibold">Group</th>
                  <th className="px-3 py-2.5 font-semibold">Member</th>
                  <th className="px-3 py-2.5 font-semibold">Month</th>
                  <th className="px-3 py-2.5 font-semibold">Amount</th>
                  <th className="px-3 py-2.5 font-semibold">Status</th>
                  <th className="px-3 py-2.5 font-semibold">Winner</th>
                </tr>
              </thead>
              <tbody>
                {payments?.map((p, i) => (
                  <tr key={`${p.groupName}-${p.memberNumber}-${p.monthNumber}-${i}`} className="border-b border-border">
                    <td className="px-3 py-2">{p.groupName}</td>
                    <td className="px-3 py-2">
                      #{p.memberNumber} {p.memberName}
                    </td>
                    <td className="px-3 py-2">{p.monthNumber}</td>
                    <td className="px-3 py-2">{formatInr(p.paymentAmount)}</td>
                    <td className="px-3 py-2">{p.paymentStatus}</td>
                    <td className="px-3 py-2">{p.winnerName ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardContent>
        </Card>
      ) : null}

      {tab === 'bids' ? (
        <Card>
          <CardHeader>
            <CardTitle>Bids / winners</CardTitle>
            <CardDescription>{bids?.length ?? 0} rows</CardDescription>
          </CardHeader>
          <CardContent className="overflow-x-auto">
            <table className="w-full min-w-[800px] text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-muted/60">
                  <th className="px-3 py-2.5 font-semibold">Group</th>
                  <th className="px-3 py-2.5 font-semibold">Month</th>
                  <th className="px-3 py-2.5 font-semibold">Winner</th>
                  <th className="px-3 py-2.5 font-semibold">Bid</th>
                  <th className="px-3 py-2.5 font-semibold">Net</th>
                  <th className="px-3 py-2.5 font-semibold">Gain/member</th>
                </tr>
              </thead>
              <tbody>
                {bids?.map((b, i) => (
                  <tr key={`${b.groupName}-${b.monthNumber}-${i}`} className="border-b border-border">
                    <td className="px-3 py-2">{b.groupName}</td>
                    <td className="px-3 py-2">{b.monthNumber}</td>
                    <td className="px-3 py-2">{b.winnerName ?? '—'}</td>
                    <td className="px-3 py-2">{formatInr(b.bidAmount)}</td>
                    <td className="px-3 py-2">{formatInr(b.netPayable)}</td>
                    <td className="px-3 py-2">{formatInr(b.gainPerMember)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardContent>
        </Card>
      ) : null}
    </div>
  )
}
