import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { useEffect, useState } from 'react'
import { ArrowLeft } from 'lucide-react'
import { useApi } from '@/shared/api/client'
import { formatInr } from '@/features/groups/types'
import type { GroupBcChart } from '@/features/bidding/types'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

type RowEdit = {
  monthNumber: number
  randomAmount: string
  boliStartAmount: string
}

export function AdminBcChartPage() {
  const { groupId } = useParams()
  const id = Number(groupId)
  const api = useApi()
  const qc = useQueryClient()
  const [step, setStep] = useState('1000')
  const [rows, setRows] = useState<RowEdit[]>([])
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['bc-chart', id],
    queryFn: () => api.get<GroupBcChart>(`/api/groups/${id}/bc-chart`),
    enabled: id > 0,
  })

  useEffect(() => {
    if (!data) return
    setStep(String(data.boliStepAmount))
    setRows(
      data.months.map((m) => ({
        monthNumber: m.monthNumber,
        randomAmount: String(m.randomAmount),
        boliStartAmount: m.boliStartAmount == null ? '' : String(m.boliStartAmount),
      })),
    )
  }, [data])

  const saveMutation = useMutation({
    mutationFn: () =>
      api.put<GroupBcChart>(`/api/groups/${id}/bc-chart`, {
        boliStepAmount: Number(step),
        months: rows.map((r) => ({
          monthNumber: r.monthNumber,
          randomAmount: Number(r.randomAmount),
          boliStartAmount: r.boliStartAmount.trim() === '' ? null : Number(r.boliStartAmount),
        })),
      }),
    onSuccess: async () => {
      setMessage('BC chart saved. Monthly bidding will use these amounts.')
      setError(null)
      await qc.invalidateQueries({ queryKey: ['bc-chart', id] })
      await qc.invalidateQueries({ queryKey: ['bidding', id] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const generateMutation = useMutation({
    mutationFn: () => api.post<GroupBcChart>(`/api/groups/${id}/bc-chart/generate-defaults`),
    onSuccess: async (next) => {
      setMessage('Default chart filled from group size / contribution (edit as needed).')
      setError(null)
      setStep(String(next.boliStepAmount))
      setRows(
        next.months.map((m) => ({
          monthNumber: m.monthNumber,
          randomAmount: String(m.randomAmount),
          boliStartAmount: m.boliStartAmount == null ? '' : String(m.boliStartAmount),
        })),
      )
      await qc.invalidateQueries({ queryKey: ['bc-chart', id] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const n = data?.totalMembers ?? 1

  return (
    <div>
      <Button asChild variant="ghost" className="mb-4 px-0 text-muted-foreground">
        <Link to={`/admin/groups/${id}`}>
          <ArrowLeft className="h-4 w-4" />
          Back to ledger
        </Link>
      </Button>

      <PageHeader
        title={data ? `BC Chart · ${data.groupName}` : 'BC Chart'}
        description={
          data
            ? `${data.totalMembers} members · ${formatInr(data.monthlyContribution)} / month · collection ${formatInr(data.monthlyContribution * data.totalMembers)}`
            : 'Month-wise random & boli start amounts'
        }
        actions={<Badge>Admin</Badge>}
      />

      {message ? <p className="mb-3 text-sm text-emerald-700">{message}</p> : null}
      {error ? <p className="mb-3 text-sm text-destructive">{error}</p> : null}
      {isLoading ? <p className="text-sm text-muted-foreground">Loading chart…</p> : null}

      {data ? (
        <>
          <Card className="mb-4">
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Per-boli deduction</CardTitle>
              <CardDescription>
                Each next bid must be this much lower than the current best boli (receive amount). Example: start
                85,000 with step 1,000 → 84,000 → 83,000.
              </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-wrap items-end gap-3">
              <div className="space-y-1">
                <Label htmlFor="boli-step">Deduction (₹)</Label>
                <Input id="boli-step" value={step} onChange={(e) => setStep(e.target.value)} className="w-40" />
              </div>
              <Button
                variant="outline"
                disabled={generateMutation.isPending}
                onClick={() => {
                  if (confirm('Fill / reset all months with default chart pattern for this group?')) {
                    generateMutation.mutate()
                  }
                }}
              >
                {generateMutation.isPending ? 'Generating…' : 'Fill defaults'}
              </Button>
              <Button disabled={saveMutation.isPending} onClick={() => saveMutation.mutate()}>
                {saveMutation.isPending ? 'Saving…' : 'Save chart'}
              </Button>
              <Button asChild variant="secondary">
                <Link to={`/admin/groups/${id}/bidding`}>Go to bidding</Link>
              </Button>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Month-wise amounts</CardTitle>
              <CardDescription>
                Random = winner receives if no boli (spin). Boli start = first bid receive amount. Leave boli blank for
                no-boli months (e.g. Month 1 organiser / last month).
              </CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto">
              <table className="w-full min-w-[720px] text-left text-sm">
                <thead>
                  <tr className="border-b text-muted-foreground">
                    <th className="px-2 py-2">Month</th>
                    <th className="px-2 py-2">Random amount</th>
                    <th className="px-2 py-2">Per member (random)</th>
                    <th className="px-2 py-2">Boli start</th>
                    <th className="px-2 py-2">Per member (boli)</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((r, idx) => {
                    const random = Number(r.randomAmount) || 0
                    const boli = r.boliStartAmount.trim() === '' ? null : Number(r.boliStartAmount)
                    return (
                      <tr key={r.monthNumber} className="border-b border-border/60">
                        <td className="px-2 py-2 font-medium">
                          {r.monthNumber}
                          {r.monthNumber === 1 ? (
                            <span className="ml-2 text-xs text-muted-foreground">Organiser</span>
                          ) : null}
                        </td>
                        <td className="px-2 py-2">
                          <Input
                            value={r.randomAmount}
                            onChange={(e) => {
                              const next = [...rows]
                              next[idx] = { ...r, randomAmount: e.target.value }
                              setRows(next)
                            }}
                          />
                        </td>
                        <td className="px-2 py-2 text-muted-foreground">
                          {formatInr(Math.round(random / n))}
                        </td>
                        <td className="px-2 py-2">
                          <Input
                            placeholder="No boli"
                            value={r.boliStartAmount}
                            onChange={(e) => {
                              const next = [...rows]
                              next[idx] = { ...r, boliStartAmount: e.target.value }
                              setRows(next)
                            }}
                          />
                        </td>
                        <td className="px-2 py-2 text-muted-foreground">
                          {boli == null || !Number.isFinite(boli) ? '—' : formatInr(Math.round(boli / n))}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </CardContent>
          </Card>
        </>
      ) : null}
    </div>
  )
}
