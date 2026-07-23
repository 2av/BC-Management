import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState } from 'react'
import { Search, Upload, UserPlus, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import type { GroupListItem } from '@/features/groups/types'
import type { ImportMembersResult, MemberListItem } from '@/features/members/types'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { TablePagination } from '@/components/ui/table-pagination'

const emptyForm = {
  memberName: '',
  username: '',
  phone: '',
  email: '',
  address: '',
  groupId: '',
  memberNumber: '',
  status: 'active',
  resetPassword: false,
}

export function AdminMembersPage() {
  const { t } = useTranslation()
  const api = useApi()
  const qc = useQueryClient()
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [showForm, setShowForm] = useState(false)
  const [editing, setEditing] = useState<MemberListItem | null>(null)
  const [form, setForm] = useState(emptyForm)
  const [importGroupId, setImportGroupId] = useState('')
  const [importResult, setImportResult] = useState<ImportMembersResult | null>(null)

  const { data: groups } = useQuery({
    queryKey: ['groups'],
    queryFn: () => api.get<GroupListItem[]>('/api/groups'),
  })

  function isRosterLocked(groupId: number) {
    const g = groups?.find((x) => x.id === groupId)
    if (!g) return false
    return g.status === 'completed' || g.completedMonths > 0
  }

  const editableGroups = useMemo(
    () => (groups ?? []).filter((g) => g.status !== 'completed' && g.completedMonths === 0),
    [groups],
  )

  const { data: members, isLoading } = useQuery({
    queryKey: ['members'],
    queryFn: () => api.get<MemberListItem[]>('/api/members'),
  })

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase()
    return (members ?? []).filter((m) => {
      if (statusFilter !== 'all' && m.status !== statusFilter) return false
      if (!q) return true
      return (
        m.memberName.toLowerCase().includes(q) ||
        (m.username?.toLowerCase().includes(q) ?? false) ||
        (m.phone?.includes(q) ?? false) ||
        (m.email?.toLowerCase().includes(q) ?? false) ||
        m.groups.some((g) => g.groupName.toLowerCase().includes(q))
      )
    })
  }, [members, search, statusFilter])

  const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize))
  const safePage = Math.min(page, totalPages)
  const pageRows = filtered.slice((safePage - 1) * pageSize, safePage * pageSize)

  useEffect(() => {
    setPage(1)
  }, [search, statusFilter, pageSize])

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (editing) {
        return api.put(`/api/members/${editing.id}`, {
          memberName: form.memberName,
          username: form.username || null,
          phone: form.phone || null,
          email: form.email || null,
          address: form.address || null,
          status: form.status,
          resetPassword: form.resetPassword,
        })
      }
      return api.post('/api/members', {
        memberName: form.memberName,
        username: form.username || null,
        phone: form.phone || null,
        email: form.email || null,
        address: form.address || null,
        groupId: form.groupId ? Number(form.groupId) : null,
        memberNumber: form.memberNumber ? Number(form.memberNumber) : null,
      })
    },
    onSuccess: async () => {
      setMessage(editing ? 'Member updated.' : 'Member created.')
      setError(null)
      setShowForm(false)
      setEditing(null)
      setForm(emptyForm)
      await qc.invalidateQueries({ queryKey: ['members'] })
      await qc.invalidateQueries({ queryKey: ['groups'] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const unassignMutation = useMutation({
    mutationFn: ({
      groupId,
      memberId,
      groupMemberId,
    }: {
      groupId: number
      memberId: number
      groupMemberId?: number
    }) =>
      api.delete(
        `/api/groups/${groupId}/members/${memberId}${groupMemberId ? `?groupMemberId=${groupMemberId}` : ''}`,
      ),
    onSuccess: async () => {
      setMessage('Removed from group.')
      await qc.invalidateQueries({ queryKey: ['members'] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const addHandMutation = useMutation({
    mutationFn: ({ groupId, memberId }: { groupId: number; memberId: number }) =>
      api.post(`/api/groups/${groupId}/members`, { memberId, addHand: true }),
    onSuccess: async () => {
      setMessage('Added another hand (seat) for this member.')
      setError(null)
      await qc.invalidateQueries({ queryKey: ['members'] })
      await qc.invalidateQueries({ queryKey: ['groups'] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const importMutation = useMutation({
    mutationFn: ({ groupId, csvContent }: { groupId: number; csvContent: string }) =>
      api.post<ImportMembersResult>(`/api/groups/${groupId}/members/import`, {
        csvContent,
        skipDuplicates: true,
      }),
    onSuccess: async (res) => {
      setImportResult(res)
      setMessage(`Import finished: ${res.imported} added, ${res.skipped} skipped.`)
      setError(null)
      await qc.invalidateQueries({ queryKey: ['members'] })
      await qc.invalidateQueries({ queryKey: ['groups'] })
    },
    onError: (e: Error) => setError(e.message),
  })

  function openCreate() {
    setEditing(null)
    setForm({
      ...emptyForm,
      groupId: editableGroups[0] ? String(editableGroups[0].id) : '',
    })
    setShowForm(true)
  }

  function openEdit(m: MemberListItem) {
    setEditing(m)
    setForm({
      memberName: m.memberName,
      username: m.username ?? '',
      phone: m.phone ?? '',
      email: m.email ?? '',
      address: m.address ?? '',
      groupId: '',
      memberNumber: '',
      status: m.status,
      resetPassword: false,
    })
    setShowForm(true)
  }

  function onCsvFile(file: File | null) {
    if (!file || !importGroupId) {
      setError('Select a group and CSV file.')
      return
    }
    const reader = new FileReader()
    reader.onload = () => {
      importMutation.mutate({ groupId: Number(importGroupId), csvContent: String(reader.result ?? '') })
    }
    reader.readAsText(file)
  }

  return (
    <div>
      <PageHeader
        title={t('pages.membersTitle')}
        description={t('pages.membersDesc')}
        actions={
          <Button onClick={openCreate}>
            <UserPlus className="h-4 w-4" />
            Add member
          </Button>
        }
      />

      {message ? <p className="mb-3 text-sm text-emerald-700">{message}</p> : null}
      {error ? <p className="mb-3 text-sm text-destructive">{error}</p> : null}

      {showForm ? (
        <Card className="mb-6">
          <CardHeader>
            <CardTitle>{editing ? 'Edit member' : 'New member'}</CardTitle>
            <CardDescription>
              Default login password is <code>member123</code>
              {editing ? '. Tick reset to restore it.' : ' unless you set another.'}
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div className="space-y-1.5">
              <Label>Name *</Label>
              <Input
                value={form.memberName}
                onChange={(e) => setForm({ ...form, memberName: e.target.value })}
              />
            </div>
            <div className="space-y-1.5">
              <Label>Username</Label>
              <Input
                value={form.username}
                placeholder="auto from name"
                onChange={(e) => setForm({ ...form, username: e.target.value })}
              />
            </div>
            <div className="space-y-1.5">
              <Label>Phone</Label>
              <Input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
            </div>
            <div className="space-y-1.5">
              <Label>Email</Label>
              <Input value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
            </div>
            <div className="space-y-1.5 sm:col-span-2">
              <Label>Address</Label>
              <Input value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
            </div>
            {!editing ? (
              <>
                <div className="space-y-1.5">
                  <Label>Assign to group</Label>
                  <select
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    value={form.groupId}
                    onChange={(e) => setForm({ ...form, groupId: e.target.value })}
                  >
                    <option value="">None yet</option>
                    {editableGroups.map((g) => (
                      <option key={g.id} value={g.id}>
                        {g.groupName}
                      </option>
                    ))}
                    {(groups ?? [])
                      .filter((g) => g.status === 'completed' || g.completedMonths > 0)
                      .map((g) => (
                        <option key={g.id} value={g.id} disabled>
                          {g.groupName}
                          {g.status === 'completed' ? ' (completed)' : ' (BC started)'}
                        </option>
                      ))}
                  </select>
                </div>
                <div className="space-y-1.5">
                  <Label>Member #</Label>
                  <Input
                    type="number"
                    min={1}
                    placeholder="auto"
                    value={form.memberNumber}
                    onChange={(e) => setForm({ ...form, memberNumber: e.target.value })}
                  />
                </div>
              </>
            ) : (
              <>
                <div className="space-y-1.5">
                  <Label>Status</Label>
                  <select
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    value={form.status}
                    onChange={(e) => setForm({ ...form, status: e.target.value })}
                  >
                    <option value="active">active</option>
                    <option value="inactive">inactive</option>
                  </select>
                </div>
                <label className="flex items-center gap-2 self-end pb-2 text-sm">
                  <input
                    type="checkbox"
                    checked={form.resetPassword}
                    onChange={(e) => setForm({ ...form, resetPassword: e.target.checked })}
                  />
                  Reset password to member123
                </label>
              </>
            )}
            <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-3">
              <Button disabled={!form.memberName || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
                {editing ? 'Save changes' : 'Create'}
              </Button>
              <Button
                variant="ghost"
                onClick={() => {
                  setShowForm(false)
                  setEditing(null)
                }}
              >
                Cancel
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : null}

      <Card className="mb-6">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Upload className="h-4 w-4" />
            CSV import
          </CardTitle>
          <CardDescription>
            Columns: <code>member_name,member_number,username,phone,email,address</code>
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-wrap items-end gap-3">
          <div className="space-y-1.5">
            <Label>Group</Label>
            <select
              className="flex h-10 min-w-48 rounded-md border border-input bg-background px-3 text-sm"
              value={importGroupId}
              onChange={(e) => setImportGroupId(e.target.value)}
            >
              <option value="">Select group</option>
              {editableGroups.map((g) => (
                <option key={g.id} value={g.id}>
                  {g.groupName}
                </option>
              ))}
              {(groups ?? [])
                .filter((g) => g.status === 'completed' || g.completedMonths > 0)
                .map((g) => (
                  <option key={g.id} value={g.id} disabled>
                    {g.groupName}
                    {g.status === 'completed' ? ' (completed)' : ' (BC started)'}
                  </option>
                ))}
            </select>
          </div>
          <div className="space-y-1.5">
            <Label>CSV file</Label>
            <Input
              type="file"
              accept=".csv,text/csv"
              disabled={importMutation.isPending}
              onChange={(e) => onCsvFile(e.target.files?.[0] ?? null)}
            />
          </div>
          {importResult ? (
            <p className="text-sm text-muted-foreground">
              Imported {importResult.imported}, skipped {importResult.skipped}
              {importResult.errors.length ? ` · ${importResult.errors.length} error(s)` : ''}
            </p>
          ) : null}
        </CardContent>
        {importResult?.errors.length ? (
          <CardContent className="pt-0">
            <ul className="max-h-28 list-disc overflow-auto pl-5 text-xs text-destructive">
              {importResult.errors.slice(0, 20).map((err) => (
                <li key={err}>{err}</li>
              ))}
            </ul>
          </CardContent>
        ) : null}
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Directory</CardTitle>
          <CardDescription>
            {filtered.length} member{filtered.length === 1 ? '' : 's'}
            {statusFilter !== 'all' || search ? ' (filtered)' : ''}.
          </CardDescription>
          <div className="mt-3 grid gap-3 rounded-xl border border-border bg-muted/40 p-3 sm:grid-cols-3">
            <div className="space-y-1.5 sm:col-span-2">
              <Label htmlFor="member-search">Search</Label>
              <div className="relative">
                <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  id="member-search"
                  className="pl-8"
                  placeholder="Name, username, phone, group"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                />
              </div>
            </div>
            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <Label htmlFor="member-status">Status</Label>
                {search || statusFilter !== 'all' ? (
                  <button
                    type="button"
                    className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                    onClick={() => {
                      setSearch('')
                      setStatusFilter('all')
                    }}
                  >
                    <X className="h-3 w-3" />
                    Clear
                  </button>
                ) : null}
              </div>
              <select
                id="member-status"
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
              >
                <option value="all">All</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {isLoading ? <p className="mb-3 text-sm text-muted-foreground">Loading members…</p> : null}
          <div className="overflow-x-auto rounded-xl border border-border">
            <table className="w-full min-w-[900px] border-collapse text-left text-sm">
              <thead>
                <tr className="border-b border-border bg-muted/60">
                  <th className="px-3 py-3 font-semibold">Member</th>
                  <th className="px-3 py-3 font-semibold">Username</th>
                  <th className="px-3 py-3 font-semibold">Contact</th>
                  <th className="px-3 py-3 font-semibold">Groups</th>
                  <th className="px-3 py-3 font-semibold">Status</th>
                  <th className="px-3 py-3 text-right font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {pageRows.map((m) => (
                  <tr key={m.id} className="border-b border-border last:border-0 hover:bg-muted/40">
                    <td className="px-3 py-2.5 font-medium">{m.memberName}</td>
                    <td className="px-3 py-2.5 text-muted-foreground">@{m.username ?? '—'}</td>
                    <td className="px-3 py-2.5 text-muted-foreground">
                      <div>{m.phone ?? '—'}</div>
                      <div className="text-xs">{m.email ?? ''}</div>
                    </td>
                    <td className="px-3 py-2.5">
                      <div className="flex flex-wrap gap-1">
                        {m.groups.length === 0 ? (
                          <span className="text-xs text-muted-foreground">Unassigned</span>
                        ) : (
                          m.groups.map((g) => (
                            <Badge
                              key={g.groupMemberId}
                              variant={g.status === 'active' ? 'default' : 'muted'}
                            >
                              {g.groupName} #{g.memberNumber}
                              {g.handLabel ? ` · ${g.handLabel}` : ''}
                              {g.status !== 'active' ? ' (off)' : ''}
                            </Badge>
                          ))
                        )}
                      </div>
                    </td>
                    <td className="px-3 py-2.5">
                      <Badge variant={m.status === 'active' ? 'success' : 'muted'}>{m.status}</Badge>
                    </td>
                    <td className="px-3 py-2.5">
                      <div className="flex flex-wrap justify-end gap-2">
                        <Button size="sm" variant="outline" onClick={() => openEdit(m)}>
                          Edit
                        </Button>
                        {(() => {
                          const activeGroups = m.groups.filter((g) => g.status === 'active')
                          const uniqueGroupIds = [...new Set(activeGroups.map((g) => g.groupId))]
                          return (
                            <>
                              {uniqueGroupIds.map((groupId) => {
                                const seats = activeGroups.filter((g) => g.groupId === groupId)
                                const first = seats[0]
                                const locked = isRosterLocked(groupId)
                                const lockReason = (() => {
                                  const g = groups?.find((x) => x.id === groupId)
                                  if (!g) return ''
                                  if (g.status === 'completed') return 'Group completed'
                                  if (g.completedMonths > 0) return 'BC started'
                                  return ''
                                })()
                                return (
                                  <span key={groupId} className="flex flex-wrap items-center gap-1">
                                    <Button
                                      size="sm"
                                      variant="secondary"
                                      disabled={locked || addHandMutation.isPending}
                                      title={locked ? lockReason : undefined}
                                      onClick={() => {
                                        if (
                                          confirm(
                                            `Add another hand for ${m.memberName} in ${first.groupName}?`,
                                          )
                                        ) {
                                          addHandMutation.mutate({ groupId, memberId: m.id })
                                        }
                                      }}
                                    >
                                      Add hand · {first.groupName}
                                    </Button>
                                    {seats.map((g) => (
                                      <Button
                                        key={g.groupMemberId}
                                        size="sm"
                                        variant="ghost"
                                        disabled={locked || unassignMutation.isPending}
                                        title={locked ? lockReason : undefined}
                                        onClick={() => {
                                          if (
                                            confirm(
                                              `Remove ${m.memberName}${g.handLabel ? ` · ${g.handLabel}` : ''} (#${g.memberNumber}) from ${g.groupName}?`,
                                            )
                                          ) {
                                            unassignMutation.mutate({
                                              groupId: g.groupId,
                                              memberId: m.id,
                                              groupMemberId: g.groupMemberId,
                                            })
                                          }
                                        }}
                                      >
                                        Remove #{g.memberNumber}
                                      </Button>
                                    ))}
                                    {locked ? (
                                      <span className="text-xs text-muted-foreground">({lockReason})</span>
                                    ) : null}
                                  </span>
                                )
                              })}
                            </>
                          )
                        })()}
                      </div>
                    </td>
                  </tr>
                ))}
                {!isLoading && pageRows.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-3 py-10 text-center text-muted-foreground">
                      No members found.
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
    </div>
  )
}
