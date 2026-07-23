import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { formatInr } from '@/features/groups/types'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

type Client = {
  id: number
  clientName: string
  companyName: string | null
  contactPerson: string
  email: string
  phone: string
  status: string
  subscriptionStatus: string | null
  subscriptionEndDate: string | null
  groupCount: number
  memberCount: number
}

type ClientDetail = Client & {
  address: string | null
  city: string | null
  state: string | null
  country: string | null
  pincode: string | null
  maxGroups: number | null
  maxMembersPerGroup: number | null
  subscriptions: {
    id: number
    planId: number
    planName: string
    startDate: string
    endDate: string
    status: string
    paymentAmount: number
  }[]
}

type EditForm = {
  clientName: string
  companyName: string
  contactPerson: string
  email: string
  phone: string
  address: string
  city: string
  state: string
  country: string
  pincode: string
  maxGroups: string
  maxMembersPerGroup: string
  status: string
}

export function SuperAdminClientsPage() {
  const { t } = useTranslation()
  const api = useApi()
  const qc = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [detailId, setDetailId] = useState<number | null>(null)
  const [form, setForm] = useState({
    clientName: '',
    companyName: '',
    contactPerson: '',
    email: '',
    phone: '',
    adminUsername: '',
    adminPassword: 'admin123',
  })
  const [editForm, setEditForm] = useState<EditForm | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['sa-clients'],
    queryFn: () => api.get<Client[]>('/api/super-admin/clients'),
  })

  const { data: detail } = useQuery({
    queryKey: ['sa-client', detailId],
    queryFn: () => api.get<ClientDetail>(`/api/super-admin/clients/${detailId}`),
    enabled: detailId != null,
  })

  const create = useMutation({
    mutationFn: () => api.post('/api/super-admin/clients', form),
    onSuccess: async () => {
      setShowForm(false)
      setMessage('Client created.')
      setError(null)
      await qc.invalidateQueries({ queryKey: ['sa-clients'] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const update = useMutation({
    mutationFn: () =>
      api.put(`/api/super-admin/clients/${editingId}`, {
        clientName: editForm!.clientName,
        companyName: editForm!.companyName || null,
        contactPerson: editForm!.contactPerson,
        email: editForm!.email,
        phone: editForm!.phone,
        address: editForm!.address || null,
        city: editForm!.city || null,
        state: editForm!.state || null,
        country: editForm!.country || null,
        pincode: editForm!.pincode || null,
        maxGroups: editForm!.maxGroups ? Number(editForm!.maxGroups) : null,
        maxMembersPerGroup: editForm!.maxMembersPerGroup ? Number(editForm!.maxMembersPerGroup) : null,
        status: editForm!.status,
      }),
    onSuccess: async () => {
      setEditingId(null)
      setEditForm(null)
      setMessage('Client updated.')
      setError(null)
      await qc.invalidateQueries({ queryKey: ['sa-clients'] })
      await qc.invalidateQueries({ queryKey: ['sa-client'] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const toggle = useMutation({
    mutationFn: (id: number) => api.patch(`/api/super-admin/clients/${id}/status`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['sa-clients'] }),
  })

  async function openEdit(id: number) {
    setError(null)
    const d = await api.get<ClientDetail>(`/api/super-admin/clients/${id}`)
    setEditingId(id)
    setEditForm({
      clientName: d.clientName,
      companyName: d.companyName ?? '',
      contactPerson: d.contactPerson,
      email: d.email,
      phone: d.phone,
      address: d.address ?? '',
      city: d.city ?? '',
      state: d.state ?? '',
      country: d.country ?? '',
      pincode: d.pincode ?? '',
      maxGroups: d.maxGroups != null ? String(d.maxGroups) : '',
      maxMembersPerGroup: d.maxMembersPerGroup != null ? String(d.maxMembersPerGroup) : '',
      status: d.status,
    })
  }

  return (
    <div>
      <PageHeader
        title={t('superAdmin.clientsTitle')}
        description={t('pages.clientsDesc')}
        actions={
          <Button
            onClick={() => {
              setShowForm((v) => !v)
              setEditingId(null)
            }}
          >
            {showForm ? 'Close' : 'Add client'}
          </Button>
        }
      />

      {message ? <p className="mb-3 text-sm text-emerald-700">{message}</p> : null}
      {error ? <p className="mb-3 text-sm text-destructive">{error}</p> : null}

      {showForm ? (
        <Card className="mb-6">
          <CardHeader>
            <CardTitle>New client</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-3 md:grid-cols-2">
            {(['clientName', 'companyName', 'contactPerson', 'email', 'phone', 'adminUsername', 'adminPassword'] as const).map(
              (k) => (
                <div key={k} className="space-y-1">
                  <Label>{k}</Label>
                  <Input value={form[k]} onChange={(e) => setForm({ ...form, [k]: e.target.value })} />
                </div>
              ),
            )}
            <Button className="md:col-span-2" disabled={create.isPending} onClick={() => create.mutate()}>
              Create client
            </Button>
          </CardContent>
        </Card>
      ) : null}

      {editForm && editingId ? (
        <Card className="mb-6">
          <CardHeader>
            <CardTitle>Edit client #{editingId}</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-3 md:grid-cols-2">
            {(
              [
                'clientName',
                'companyName',
                'contactPerson',
                'email',
                'phone',
                'address',
                'city',
                'state',
                'country',
                'pincode',
                'maxGroups',
                'maxMembersPerGroup',
              ] as const
            ).map((k) => (
              <div key={k} className="space-y-1">
                <Label>{k}</Label>
                <Input value={editForm[k]} onChange={(e) => setEditForm({ ...editForm, [k]: e.target.value })} />
              </div>
            ))}
            <div className="space-y-1">
              <Label>Status</Label>
              <select
                className="flex h-10 w-full rounded-md border px-3 text-sm"
                value={editForm.status}
                onChange={(e) => setEditForm({ ...editForm, status: e.target.value })}
              >
                <option value="active">active</option>
                <option value="inactive">inactive</option>
                <option value="suspended">suspended</option>
              </select>
            </div>
            <div className="flex gap-2 md:col-span-2">
              <Button disabled={update.isPending} onClick={() => update.mutate()}>
                Save changes
              </Button>
              <Button
                variant="outline"
                onClick={() => {
                  setEditingId(null)
                  setEditForm(null)
                }}
              >
                Cancel
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : null}

      {detailId && detail ? (
        <Card className="mb-6">
          <CardHeader>
            <div className="flex items-start justify-between gap-2">
              <div>
                <CardTitle>{detail.clientName}</CardTitle>
                <CardDescription>
                  {detail.companyName ?? detail.email} · {detail.groupCount} groups · {detail.memberCount} members
                </CardDescription>
              </div>
              <Button size="sm" variant="outline" onClick={() => setDetailId(null)}>
                Close
              </Button>
            </div>
          </CardHeader>
          <CardContent className="space-y-3 text-sm">
            <p>
              {detail.contactPerson} · {detail.phone} · {detail.email}
            </p>
            <p className="text-muted-foreground">
              {[detail.address, detail.city, detail.state, detail.country, detail.pincode].filter(Boolean).join(', ') ||
                'No address'}
            </p>
            <p>
              Limits: {detail.maxGroups ?? '—'} groups / {detail.maxMembersPerGroup ?? '—'} members per group
            </p>
            <div className="space-y-2">
              <p className="font-medium">Recent subscriptions</p>
              {detail.subscriptions.length === 0 ? (
                <p className="text-muted-foreground">No subscriptions yet.</p>
              ) : (
                detail.subscriptions.map((s) => (
                  <div key={s.id} className="rounded-lg border px-3 py-2">
                    {s.planName} · {s.startDate} → {s.endDate} · {formatInr(s.paymentAmount)} · {s.status}
                  </div>
                ))
              )}
            </div>
          </CardContent>
        </Card>
      ) : null}

      {isLoading ? <p className="text-sm text-muted-foreground">Loading…</p> : null}
      <div className="grid gap-4 md:grid-cols-2">
        {data?.map((c) => (
          <Card key={c.id}>
            <CardHeader>
              <div className="flex items-start justify-between">
                <div>
                  <CardTitle>{c.clientName}</CardTitle>
                  <CardDescription>{c.companyName ?? c.email}</CardDescription>
                </div>
                <Badge variant={c.status === 'active' ? 'success' : 'muted'}>{c.status}</Badge>
              </div>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <p>
                {c.contactPerson} · {c.phone}
              </p>
              <p>
                {c.groupCount} groups · {c.memberCount} members
              </p>
              <p className="text-muted-foreground">
                Subscription: {c.subscriptionStatus ?? '—'}
                {c.subscriptionEndDate ? ` · ends ${c.subscriptionEndDate}` : ''}
              </p>
              <div className="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" onClick={() => setDetailId(c.id)}>
                  View
                </Button>
                <Button size="sm" variant="outline" onClick={() => void openEdit(c.id)}>
                  Edit
                </Button>
                <Button size="sm" variant="secondary" onClick={() => toggle.mutate(c.id)}>
                  Toggle status
                </Button>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}
