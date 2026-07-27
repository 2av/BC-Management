import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, Dices } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { useAuth } from '@/features/auth/AuthContext'
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
}

type AvailableResponse = {
  activeMonth: number | null
  canCustomPick: boolean
  canPlacePick: boolean
  blockReason: string | null
  members: SpinMember[]
}

export function MemberRandomPicksPage() {
  const { t } = useTranslation()
  const { groupId } = useParams()
  const id = Number(groupId)
  const api = useApi()
  const { user } = useAuth()
  const qc = useQueryClient()
  const [month, setMonth] = useState('')
  const [decidedMemberId, setDecidedMemberId] = useState('')
  const [wheelOpen, setWheelOpen] = useState(false)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const { data: picks } = useQuery({
    queryKey: ['random-picks', id],
    queryFn: () => api.get<RandomPick[]>(`/api/groups/${id}/random-picks`),
    enabled: id > 0,
  })

  const { data: available } = useQuery({
    queryKey: ['random-available', id],
    queryFn: () => api.get<AvailableResponse>(`/api/groups/${id}/random-picks/available-members`),
    enabled: id > 0,
  })

  useEffect(() => {
    if (available?.activeMonth != null && !month) {
      setMonth(String(available.activeMonth === 1 ? 2 : available.activeMonth))
    }
  }, [available, month])

  const canDecideWinner = Boolean(available?.canCustomPick)
  const members = available?.members ?? []

  const confirmPick = useMutation({
    mutationFn: (member: SpinMember) => {
      const monthNumber = Number(month)
      if (canDecideWinner && decidedMemberId) {
        const seat = members.find(
          (m) => String(m.groupMemberId ?? m.memberId) === decidedMemberId,
        )
        return api.post<{ effectiveMemberName: string; monthNumber: number }>(
          `/api/groups/${id}/random-picks/custom`,
          {
            monthNumber,
            selectedMemberId: seat?.memberId ?? (Number(decidedMemberId) || member.memberId),
            selectedGroupMemberId: seat?.groupMemberId ?? member.groupMemberId,
          },
        )
      }
      return api.post<{ effectiveMemberName: string; monthNumber: number }>(
        `/api/groups/${id}/random-picks`,
        { monthNumber },
      )
    },
    onSuccess: async (res) => {
      setMessage(`${res.effectiveMemberName} selected for month ${res.monthNumber}.`)
      setError(null)
      setWheelOpen(false)
      await qc.invalidateQueries({ queryKey: ['random-picks', id] })
      await qc.invalidateQueries({ queryKey: ['random-available', id] })
    },
    onError: (e: Error) => setError(e.message),
  })

  if (available && !available.canCustomPick) {
    return (
      <div>
        <Button asChild variant="ghost" className="mb-4 px-0 text-muted-foreground">
          <Link to={`/member/groups/${id}`}>
            <ArrowLeft className="h-4 w-4" />
            Back to ledger
          </Link>
        </Button>
        <p className="text-sm text-amber-800">
          {available.blockReason ?? 'You are not allowed to run the random spin.'}
        </p>
      </div>
    )
  }

  return (
    <div>
      <Button asChild variant="ghost" className="mb-4 px-0 text-muted-foreground">
        <Link to={`/member/groups/${id}`}>
          <ArrowLeft className="h-4 w-4" />
          Back to ledger
        </Link>
      </Button>

      <PageHeader
        title={t('pages.memberRandomTitle')}
        description={
          canDecideWinner
            ? 'You can decide the winner first; spin will land on that member.'
            : t('pages.memberRandomDesc')
        }
        actions={<Badge variant="success">Member · @{user?.username}</Badge>}
      />

      {message ? <p className="mb-3 text-sm text-emerald-700">{message}</p> : null}
      {error ? <p className="mb-3 text-sm text-destructive">{error}</p> : null}
      {available?.blockReason ? (
        <p className="mb-3 text-sm text-amber-700">{available.blockReason}</p>
      ) : null}

      <Card className="mb-6 border-amber-200 bg-gradient-to-br from-amber-50 to-card">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Dices className="h-5 w-5 text-amber-600" />
            Spin wheel
          </CardTitle>
          <CardDescription>
            {members.length} eligible · active month {available?.activeMonth ?? '—'}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-1.5">
            <Label>Month</Label>
            <Input type="number" min={2} value={month} onChange={(e) => setMonth(e.target.value)} />
          </div>
          {canDecideWinner ? (
            <div className="space-y-1.5">
              <Label>Decide winner first (optional)</Label>
              <select
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                value={decidedMemberId}
                onChange={(e) => setDecidedMemberId(e.target.value)}
              >
                <option value="">True random</option>
                {members.map((m) => (
                  <option key={m.groupMemberId ?? m.memberId} value={m.groupMemberId ?? m.memberId}>
                    #{m.memberNumber} {m.memberName}
                    {m.handLabel ? ` · ${m.handLabel}` : ''}
                  </option>
                ))}
              </select>
            </div>
          ) : null}
          <Button
            size="lg"
            className="w-full bg-amber-500 text-white hover:bg-amber-600"
            disabled={!members.length || available?.canPlacePick === false}
            onClick={() => setWheelOpen(true)}
          >
            <Dices className="h-5 w-5" />
            Open spin wheel
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Previous picks</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          {picks?.map((p) => (
            <div key={p.id} className="flex justify-between rounded-lg border border-border px-3 py-2 text-sm">
              <span>Month {p.monthNumber}</span>
              <span className="font-medium">{p.effectiveMemberName}</span>
            </div>
          ))}
          {picks && picks.length === 0 ? (
            <p className="text-sm text-muted-foreground">No picks yet.</p>
          ) : null}
        </CardContent>
      </Card>

      <SpinWheelModal
        open={wheelOpen}
        members={members}
        monthNumber={Number(month) || 2}
        predeterminedGroupMemberId={
          canDecideWinner && decidedMemberId ? Number(decidedMemberId) : null
        }
        confirming={confirmPick.isPending}
        onClose={() => setWheelOpen(false)}
        onConfirm={(m) => confirmPick.mutate(m)}
      />
    </div>
  )
}
