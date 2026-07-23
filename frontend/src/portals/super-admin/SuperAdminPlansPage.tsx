import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { formatInr } from '@/features/groups/types'

type Plan = {
  id: number
  planName: string
  durationMonths: number
  price: number
  description: string | null
  features: string[]
  isActive: boolean
  isPromotional: boolean
  maxGroups: number | null
  maxMembersPerGroup: number | null
}

export function SuperAdminPlansPage() {
  const { t } = useTranslation()
  const api = useApi()
  const qc = useQueryClient()
  const [editing, setEditing] = useState<Plan | null>(null)
  const [form, setForm] = useState({
    planName: '',
    durationMonths: '1',
    price: '',
    description: '',
    features: '',
    isActive: true,
    isPromotional: false,
    maxGroups: '',
    maxMembersPerGroup: '',
  })

  const { data } = useQuery({
    queryKey: ['sa-plans'],
    queryFn: () => api.get<Plan[]>('/api/super-admin/plans'),
  })

  const save = useMutation({
    mutationFn: () =>
      editing
        ? api.put(`/api/super-admin/plans/${editing.id}`, {
            planName: form.planName,
            durationMonths: Number(form.durationMonths),
            price: Number(form.price),
            description: form.description,
            features: form.features.split('\n').filter(Boolean),
            isActive: form.isActive,
            isPromotional: form.isPromotional,
            maxGroups: form.maxGroups ? Number(form.maxGroups) : null,
            maxMembersPerGroup: form.maxMembersPerGroup ? Number(form.maxMembersPerGroup) : null,
          })
        : api.post('/api/super-admin/plans', {
            planName: form.planName,
            durationMonths: Number(form.durationMonths),
            price: Number(form.price),
            description: form.description,
            features: form.features.split('\n').filter(Boolean),
            isActive: true,
            isPromotional: form.isPromotional,
            maxGroups: form.maxGroups ? Number(form.maxGroups) : null,
            maxMembersPerGroup: form.maxMembersPerGroup ? Number(form.maxMembersPerGroup) : null,
          }),
    onSuccess: async () => {
      setEditing(null)
      await qc.invalidateQueries({ queryKey: ['sa-plans'] })
    },
  })

  return (
    <div>
      <PageHeader title={t('superAdmin.plansTitle')} description={t('pages.plansDesc')} />
      <Card className="mb-6">
        <CardHeader><CardTitle>{editing ? 'Edit plan' : 'New plan'}</CardTitle></CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2">
          <div><Label>Name</Label><Input value={form.planName} onChange={(e) => setForm({ ...form, planName: e.target.value })} /></div>
          <div><Label>Duration (months)</Label><Input type="number" value={form.durationMonths} onChange={(e) => setForm({ ...form, durationMonths: e.target.value })} /></div>
          <div><Label>Price</Label><Input type="number" value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} /></div>
          <div><Label>Max groups</Label><Input value={form.maxGroups} onChange={(e) => setForm({ ...form, maxGroups: e.target.value })} /></div>
          <div className="md:col-span-2"><Label>Description</Label><Input value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></div>
          <div className="md:col-span-2"><Label>Features (one per line)</Label><textarea className="min-h-24 w-full rounded-md border px-3 py-2 text-sm" value={form.features} onChange={(e) => setForm({ ...form, features: e.target.value })} /></div>
          <Button disabled={save.isPending} onClick={() => save.mutate()}>Save plan</Button>
        </CardContent>
      </Card>
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {data?.map((p) => (
          <Card key={p.id}>
            <CardHeader>
              <div className="flex justify-between">
                <CardTitle>{p.planName}</CardTitle>
                <Badge variant={p.isActive ? 'success' : 'muted'}>{p.isActive ? 'Active' : 'Inactive'}</Badge>
              </div>
            </CardHeader>
            <CardContent className="text-sm space-y-2">
              <p className="text-lg font-semibold">{formatInr(p.price)} / {p.durationMonths} mo</p>
              <ul className="list-disc pl-5 text-muted-foreground">{p.features.map((f) => <li key={f}>{f}</li>)}</ul>
              <Button size="sm" variant="outline" onClick={() => { setEditing(p); setForm({ planName: p.planName, durationMonths: String(p.durationMonths), price: String(p.price), description: p.description ?? '', features: p.features.join('\n'), isActive: p.isActive, isPromotional: p.isPromotional, maxGroups: p.maxGroups ? String(p.maxGroups) : '', maxMembersPerGroup: p.maxMembersPerGroup ? String(p.maxMembersPerGroup) : '' }) }}>Edit</Button>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}
