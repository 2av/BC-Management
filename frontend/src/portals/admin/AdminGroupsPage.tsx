import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { formatInr, type GroupListItem } from '@/features/groups/types'
import { MemberSlotsPicker, type MemberSlotValue } from '@/features/groups/MemberSlotsPicker'
import type { GroupMemberRosterItem, MemberListItem } from '@/features/members/types'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function AdminGroupsPage() {
  const { t } = useTranslation()
  const api = useApi()
  const qc = useQueryClient()
  const [showCreate, setShowCreate] = useState(false)
  const [editId, setEditId] = useState<number | null>(null)
  const [cloneId, setCloneId] = useState<number | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const [name, setName] = useState('')
  const [members, setMembers] = useState(5)
  const [contribution, setContribution] = useState('5000')
  const [startDate, setStartDate] = useState(new Date().toISOString().slice(0, 10))
  const [slots, setSlots] = useState<MemberSlotValue[]>(() => Array.from({ length: 5 }, () => null))
  const [editName, setEditName] = useState('')
  const [editDate, setEditDate] = useState('')
  const [editStatus, setEditStatus] = useState('active')
  const [editAssignSlot, setEditAssignSlot] = useState<MemberSlotValue[]>([null])
  const [cloneName, setCloneName] = useState('')
  const [cloneDate, setCloneDate] = useState(new Date().toISOString().slice(0, 10))

  const { data, isLoading, error: loadError } = useQuery({
    queryKey: ['groups'],
    queryFn: () => api.get<GroupListItem[]>('/api/groups'),
  })

  const { data: directory } = useQuery({
    queryKey: ['members'],
    queryFn: () => api.get<MemberListItem[]>('/api/members'),
  })

  const editingGroup = data?.find((g) => g.id === editId) ?? null
  const editRosterLocked =
    !!editingGroup && (editingGroup.status === 'completed' || editingGroup.completedMonths > 0)

  const { data: editRoster } = useQuery({
    queryKey: ['group-members', editId],
    queryFn: () => api.get<GroupMemberRosterItem[]>(`/api/groups/${editId}/members`),
    enabled: editId != null,
  })

  const collection = useMemo(
    () => Number(contribution || 0) * Number(members || 0),
    [contribution, members],
  )

  const createReady =
    !!name.trim() &&
    slots.length === members &&
    slots.every((s) => s != null)

  const createMutation = useMutation({
    mutationFn: () =>
      api.post('/api/groups', {
        groupName: name,
        totalMembers: members,
        monthlyContribution: Number(contribution),
        startDate,
        members: slots
          .filter((s): s is NonNullable<MemberSlotValue> => s != null)
          .map((s) =>
            s.kind === 'existing'
              ? { memberId: s.memberId as number | null, memberName: null as string | null }
              : { memberId: null as number | null, memberName: s.memberName },
          ),
      }),
    onSuccess: async () => {
      setMessage('Group created.')
      setError(null)
      setShowCreate(false)
      setSlots(Array.from({ length: members }, () => null))
      await qc.invalidateQueries({ queryKey: ['groups'] })
      await qc.invalidateQueries({ queryKey: ['members'] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const updateMutation = useMutation({
    mutationFn: () =>
      api.put(`/api/groups/${editId}`, {
        groupName: editName,
        startDate: editDate,
        status: editStatus,
      }),
    onSuccess: async () => {
      setMessage('Group updated.')
      await qc.invalidateQueries({ queryKey: ['groups'] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const assignMutation = useMutation({
    mutationFn: async () => {
      const slot = editAssignSlot[0]
      if (!editId || !slot) throw new Error('Pick a member first.')
      if (slot.kind === 'existing') {
        const already = editRoster?.some((r) => r.memberId === slot.memberId)
        return api.post(`/api/groups/${editId}/members`, {
          memberId: slot.memberId,
          addHand: Boolean(already),
        })
      }
      return api.post(`/api/groups/${editId}/members`, {
        memberName: slot.memberName,
        addHand: false,
      })
    },
    onSuccess: async () => {
      setMessage('Member added to group.')
      setError(null)
      setEditAssignSlot([null])
      await qc.invalidateQueries({ queryKey: ['group-members', editId] })
      await qc.invalidateQueries({ queryKey: ['groups'] })
      await qc.invalidateQueries({ queryKey: ['members'] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const cloneMutation = useMutation({
    mutationFn: async () => {
      const roster = await api.get<{ memberId: number }[]>(`/api/groups/${cloneId}/members`)
      return api.post(`/api/groups/${cloneId}/clone`, {
        newGroupName: cloneName,
        startDate: cloneDate,
        selectedMemberIds: [...new Set(roster.map((r) => r.memberId))],
        newMemberNames: [],
      })
    },
    onSuccess: async () => {
      setMessage('Group cloned.')
      setCloneId(null)
      await qc.invalidateQueries({ queryKey: ['groups'] })
    },
    onError: (e: Error) => setError(e.message),
  })

  useEffect(() => {
    setSlots((prev) => {
      const next = prev.slice(0, members)
      while (next.length < members) next.push(null)
      return next
    })
  }, [members])

  function openEdit(g: GroupListItem) {
    setEditId(g.id)
    setEditName(g.groupName)
    setEditDate(g.startDate.slice(0, 10))
    setEditStatus(g.status)
    setEditAssignSlot([null])
    setShowCreate(false)
    setCloneId(null)
  }

  return (
    <div>
      <PageHeader
        title={t('pages.groupsTitle')}
        description={t('pages.groupsDesc')}
        actions={
          <Button
            onClick={() => {
              setShowCreate((v) => !v)
              setEditId(null)
              setCloneId(null)
            }}
          >
            <Plus className="h-4 w-4" />
            New group
          </Button>
        }
      />

      {message ? <p className="mb-3 text-sm text-emerald-700">{message}</p> : null}
      {error || loadError ? (
        <p className="mb-3 text-sm text-destructive">{error ?? (loadError as Error).message}</p>
      ) : null}

      {showCreate ? (
        <Card className="mb-6">
          <CardContent className="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
            <div className="space-y-1.5 sm:col-span-2 lg:col-span-1">
              <Label>Group name</Label>
              <Input value={name} onChange={(e) => setName(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Members (2–50)</Label>
              <Input
                type="number"
                min={2}
                max={50}
                value={members}
                onChange={(e) => setMembers(Number(e.target.value))}
              />
            </div>
            <div className="space-y-1.5">
              <Label>Monthly contribution</Label>
              <Input value={contribution} onChange={(e) => setContribution(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Start date</Label>
              <Input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Collection</Label>
              <p className="flex h-10 items-center font-medium">{formatInr(collection)}</p>
            </div>
            <MemberSlotsPicker
              slotCount={members}
              members={directory ?? []}
              value={slots}
              onChange={setSlots}
            />
            <div className="flex gap-2 sm:col-span-2 lg:col-span-3">
              <Button
                disabled={!createReady || createMutation.isPending}
                onClick={() => createMutation.mutate()}
              >
                Create group
              </Button>
              <Button variant="ghost" onClick={() => setShowCreate(false)}>
                Cancel
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : null}

      {editId ? (
        <Card className="mb-6">
          <CardContent className="grid gap-3 p-5 sm:grid-cols-4">
            <div className="space-y-1.5 sm:col-span-2">
              <Label>Name</Label>
              <Input value={editName} onChange={(e) => setEditName(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Start date</Label>
              <Input type="date" value={editDate} onChange={(e) => setEditDate(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Status</Label>
              <select
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                value={editStatus}
                onChange={(e) => setEditStatus(e.target.value)}
              >
                <option value="active">active</option>
                <option value="completed">completed</option>
              </select>
            </div>

            <div className="space-y-2 sm:col-span-4">
              <Label>Current roster</Label>
              <div className="flex flex-wrap gap-1.5">
                {(editRoster ?? []).map((r) => (
                  <Badge key={r.groupMemberId} variant={r.status === 'active' ? 'default' : 'muted'}>
                    #{r.memberNumber} {r.memberName}
                    {r.handLabel ? ` · ${r.handLabel}` : ''}
                  </Badge>
                ))}
                {(editRoster ?? []).length === 0 ? (
                  <span className="text-sm text-muted-foreground">No seats yet.</span>
                ) : null}
              </div>
            </div>

            {!editRosterLocked ? (
              <div className="sm:col-span-4">
                <MemberSlotsPicker
                  slotCount={1}
                  members={directory ?? []}
                  value={editAssignSlot}
                  onChange={setEditAssignSlot}
                />
                <p className="mb-2 text-xs text-muted-foreground">
                  Add an existing member (extra hand if already in group) or create a new login.
                </p>
                <Button
                  size="sm"
                  variant="secondary"
                  disabled={!editAssignSlot[0] || assignMutation.isPending}
                  onClick={() => assignMutation.mutate()}
                >
                  Add to group
                </Button>
              </div>
            ) : (
              <p className="text-sm text-muted-foreground sm:col-span-4">
                Roster is locked because this group is completed or the BC has started. Manage from{' '}
                <Link className="text-primary underline" to="/admin/members">
                  Members
                </Link>{' '}
                only for profile edits.
              </p>
            )}

            <div className="flex gap-2 sm:col-span-4">
              <Button onClick={() => updateMutation.mutate()}>Save</Button>
              <Button variant="ghost" onClick={() => setEditId(null)}>
                Cancel
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : null}

      {cloneId ? (
        <Card className="mb-6">
          <CardContent className="grid gap-3 p-5 sm:grid-cols-3">
            <div className="space-y-1.5 sm:col-span-2">
              <Label>New group name</Label>
              <Input value={cloneName} onChange={(e) => setCloneName(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Start date</Label>
              <Input type="date" value={cloneDate} onChange={(e) => setCloneDate(e.target.value)} />
            </div>
            <p className="text-sm text-muted-foreground sm:col-span-3">
              Clones contribution and copies the current roster into a new group.
            </p>
            <div className="flex gap-2 sm:col-span-3">
              <Button disabled={!cloneName || cloneMutation.isPending} onClick={() => cloneMutation.mutate()}>
                Clone
              </Button>
              <Button variant="ghost" onClick={() => setCloneId(null)}>
                Cancel
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : null}

      {isLoading ? <p className="text-sm text-muted-foreground">Loading groups…</p> : null}

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {data?.map((g) => (
          <Card key={g.id} className="transition-shadow hover:shadow-md">
            <CardContent className="space-y-4 p-5">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <h3 className="font-semibold text-foreground">{g.groupName}</h3>
                  <p className="mt-1 text-sm text-muted-foreground">
                    Started {new Date(g.startDate).toLocaleDateString('en-IN')}
                  </p>
                </div>
                <Badge variant={g.status === 'active' ? 'success' : 'muted'}>{g.status}</Badge>
              </div>
              <div className="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <p className="text-muted-foreground">Members</p>
                  <p className="font-medium">{g.totalMembers}</p>
                </div>
                <div>
                  <p className="text-muted-foreground">Monthly</p>
                  <p className="font-medium">{formatInr(g.monthlyContribution)}</p>
                </div>
                <div>
                  <p className="text-muted-foreground">Progress</p>
                  <p className="font-medium">
                    {g.completedMonths}/{g.totalMembers}
                  </p>
                </div>
                <div>
                  <p className="text-muted-foreground">Pending</p>
                  <p className="font-medium">{formatInr(g.pendingAmount)}</p>
                </div>
              </div>
              <div className="flex flex-wrap gap-2">
                <Button asChild size="sm" variant="outline">
                  <Link to={`/admin/groups/${g.id}`}>Ledger</Link>
                </Button>
                <Button asChild size="sm" variant="outline">
                  <Link to={`/admin/groups/${g.id}/bidding`}>Bidding</Link>
                </Button>
                <Button asChild size="sm" variant="outline">
                  <Link to={`/admin/groups/${g.id}/random-picks`}>Picks</Link>
                </Button>
                <Button size="sm" variant="ghost" onClick={() => openEdit(g)}>
                  Edit
                </Button>
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => {
                    setCloneId(g.id)
                    setCloneName(`${g.groupName} (copy)`)
                    setEditId(null)
                    setShowCreate(false)
                  }}
                >
                  Clone
                </Button>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}
