import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, Dices } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { SpinWheelModal, type SpinMember } from '@/components/spin/SpinWheelModal'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

type RandomPick = {
  id: number
  monthNumber: number
  selectedMemberName: string
  adminOverrideMemberName: string | null
  effectiveMemberName: string
  pickedByType: string
  pickedAt: string
}

type AvailableResponse = {
  activeMonth: number | null
  canCustomPick: boolean
  canPlacePick: boolean
  blockReason: string | null
  members: SpinMember[]
}

type RandomPickResult = { effectiveMemberName: string; monthNumber: number }

export function AdminRandomPicksPage() {
  const { t } = useTranslation()
  const { groupId } = useParams()
  const id = Number(groupId)
  const api = useApi()
  const qc = useQueryClient()
  const [month, setMonth] = useState('')
  const [wheelOpen, setWheelOpen] = useState(false)
  const [overrideMemberId, setOverrideMemberId] = useState('')
  const [overrideMonth, setOverrideMonth] = useState('')
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const { data: picks, isLoading } = useQuery({
    queryKey: ['random-picks', id],
    queryFn: () => api.get<RandomPick[]>(`/api/groups/${id}/random-picks`),
    enabled: id > 0,
  })

  const { data: available } = useQuery({
    queryKey: ['random-available', id],
    queryFn: () => api.get<AvailableResponse>(`/api/groups/${id}/random-picks/available-members`),
    enabled: id > 0,
  })

  const members = available?.members ?? []

  useEffect(() => {
    if (available?.activeMonth != null && !month) {
      setMonth(String(available.activeMonth === 1 ? 2 : available.activeMonth))
    }
  }, [available, month])

  const confirmPick = useMutation({
    mutationFn: (member: SpinMember) =>
      api.post<RandomPickResult>(`/api/groups/${id}/random-picks/custom`, {
        monthNumber: Number(month),
        selectedMemberId: member.memberId,
        selectedGroupMemberId: member.groupMemberId,
      }),
    onSuccess: async (res) => {
      setMessage(`${res.effectiveMemberName} selected for month ${res.monthNumber}.`)
      setError(null)
      setWheelOpen(false)
      await qc.invalidateQueries({ queryKey: ['random-picks', id] })
      await qc.invalidateQueries({ queryKey: ['random-available', id] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const override = useMutation({
    mutationFn: () => {
      const seat = members.find(
        (m) => String(m.groupMemberId ?? m.memberId) === overrideMemberId,
      )
      return api.put(`/api/groups/${id}/random-picks/${overrideMonth}/override`, {
        memberId: seat?.memberId ?? Number(overrideMemberId),
        groupMemberId: seat?.groupMemberId,
      })
    },
    onSuccess: async () => {
      setMessage('Override saved.')
      await qc.invalidateQueries({ queryKey: ['random-picks', id] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const clearOverride = useMutation({
    mutationFn: (m: number) => api.delete(`/api/groups/${id}/random-picks/${m}/override`),
    onSuccess: async () => {
      setMessage('Override cleared.')
      await qc.invalidateQueries({ queryKey: ['random-picks', id] })
    },
    onError: (e: Error) => setError(e.message),
  })

  return (
    <div>
      <Button asChild variant="ghost" className="mb-4 px-0 text-muted-foreground">
        <Link to="/admin/groups">
          <ArrowLeft className="h-4 w-4" />
          Back to groups
        </Link>
      </Button>

      <PageHeader
        title={t('pages.randomPicksTitle')}
        description={t('pages.randomPicksDesc')}
        actions={<Badge>Admin</Badge>}
      />

      {message ? <p className="mb-3 text-sm text-emerald-700">{message}</p> : null}
      {error ? <p className="mb-3 text-sm text-destructive">{error}</p> : null}
      {available?.blockReason ? (
        <p className="mb-3 text-sm text-amber-700">{available.blockReason}</p>
      ) : null}

      <div className="mb-6 grid gap-4 lg:grid-cols-2">
        <Card className="overflow-hidden border-amber-200 bg-gradient-to-br from-amber-50 to-card">
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-navy">
              <Dices className="h-5 w-5 text-amber-600" />
              Spin wheel
            </CardTitle>
            <CardDescription>
              {members.length} eligible · active month {available?.activeMonth ?? '—'}
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-1.5">
              <Label>Month number</Label>
              <Input type="number" min={2} value={month} onChange={(e) => setMonth(e.target.value)} />
            </div>
            <Button
              size="lg"
              className="w-full bg-amber-500 text-white hover:bg-amber-600 hover:scale-[1.02] transition-transform"
              disabled={!members.length || available?.canPlacePick === false}
              onClick={() => {
                setError(null)
                setWheelOpen(true)
              }}
            >
              <Dices className="h-5 w-5" />
              Open spin wheel
            </Button>
            {!members.length ? (
              <p className="text-sm text-muted-foreground">
                All members have already received an amount, or no roster is available.
              </p>
            ) : null}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Admin override</CardTitle>
            <CardDescription>Replace the effective pick for a month that already has a spin.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="space-y-1.5">
              <Label>Month</Label>
              <Input value={overrideMonth} onChange={(e) => setOverrideMonth(e.target.value)} placeholder="e.g. 3" />
            </div>
            <div className="space-y-1.5">
              <Label>Member</Label>
              <select
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                value={overrideMemberId}
                onChange={(e) => setOverrideMemberId(e.target.value)}
              >
                <option value="">Select…</option>
                {members.map((m) => (
                  <option key={m.groupMemberId ?? m.memberId} value={m.groupMemberId ?? m.memberId}>
                    #{m.memberNumber} {m.memberName}
                    {m.handLabel ? ` · ${m.handLabel}` : ''}
                  </option>
                ))}
              </select>
            </div>
            <Button
              disabled={!overrideMonth || !overrideMemberId || override.isPending}
              onClick={() => override.mutate()}
            >
              Apply override
            </Button>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>History</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? <p className="text-sm text-muted-foreground">Loading…</p> : null}
          <div className="overflow-x-auto rounded-xl border border-border">
            <table className="w-full min-w-[700px] text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-muted/60">
                  <th className="px-3 py-3 font-semibold">Month</th>
                  <th className="px-3 py-3 font-semibold">Picked</th>
                  <th className="px-3 py-3 font-semibold">Override</th>
                  <th className="px-3 py-3 font-semibold">Effective</th>
                  <th className="px-3 py-3 font-semibold">By</th>
                  <th className="px-3 py-3 text-right font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {picks?.map((p) => (
                  <tr key={p.id} className="border-b border-border last:border-0">
                    <td className="px-3 py-2.5">M{p.monthNumber}</td>
                    <td className="px-3 py-2.5">{p.selectedMemberName}</td>
                    <td className="px-3 py-2.5">{p.adminOverrideMemberName ?? '—'}</td>
                    <td className="px-3 py-2.5 font-medium">{p.effectiveMemberName}</td>
                    <td className="px-3 py-2.5 text-muted-foreground">{p.pickedByType}</td>
                    <td className="px-3 py-2.5 text-right">
                      {p.adminOverrideMemberName ? (
                        <Button size="sm" variant="ghost" onClick={() => clearOverride.mutate(p.monthNumber)}>
                          Clear override
                        </Button>
                      ) : (
                        '—'
                      )}
                    </td>
                  </tr>
                ))}
                {picks && picks.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-3 py-8 text-center text-muted-foreground">
                      No random picks yet.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

      <SpinWheelModal
        open={wheelOpen}
        members={members}
        monthNumber={Number(month) || 2}
        confirming={confirmPick.isPending}
        onClose={() => setWheelOpen(false)}
        onConfirm={(member) => confirmPick.mutate(member)}
      />
    </div>
  )
}
