import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { formatInr } from '@/features/groups/types'

type Sub = { id: number; planId: number; planName: string; startDate: string; endDate: string; status: string; paymentAmount: number }
type Client = { id: number; clientName: string }
type Plan = { id: number; planName: string; price: number }

export function SuperAdminSubscriptionsPage() {
  const { t } = useTranslation()
  const api = useApi()
  const qc = useQueryClient()
  const [clientId, setClientId] = useState('')
  const [planId, setPlanId] = useState('')
  const [amount, setAmount] = useState('')

  const { data: subs } = useQuery({ queryKey: ['sa-subs'], queryFn: () => api.get<Sub[]>('/api/super-admin/subscriptions') })
  const { data: clients } = useQuery({ queryKey: ['sa-clients'], queryFn: () => api.get<Client[]>('/api/super-admin/clients') })
  const { data: plans } = useQuery({ queryKey: ['sa-plans'], queryFn: () => api.get<Plan[]>('/api/super-admin/plans') })

  const assign = useMutation({
    mutationFn: () => api.post('/api/super-admin/subscriptions', {
      clientId: Number(clientId), planId: Number(planId), paymentAmount: Number(amount), paymentMethod: 'manual',
    }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['sa-subs'] }),
  })

  const extend = useMutation({
    mutationFn: (id: number) => api.post(`/api/super-admin/subscriptions/${id}/extend`, { months: 3 }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['sa-subs'] }),
  })

  const cancel = useMutation({
    mutationFn: (id: number) => api.post(`/api/super-admin/subscriptions/${id}/cancel`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['sa-subs'] }),
  })

  return (
    <div>
      <PageHeader title={t('superAdmin.subscriptionsTitle')} description={t('pages.subscriptionsDesc')} />
      <Card className="mb-6">
        <CardHeader><CardTitle>Assign plan</CardTitle></CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-4">
          <div><Label>Client</Label><select className="h-10 w-full rounded-md border px-3 text-sm" value={clientId} onChange={(e) => setClientId(e.target.value)}><option value="">Select</option>{clients?.map((c) => <option key={c.id} value={c.id}>{c.clientName}</option>)}</select></div>
          <div><Label>Plan</Label><select className="h-10 w-full rounded-md border px-3 text-sm" value={planId} onChange={(e) => { setPlanId(e.target.value); const p = plans?.find((x) => x.id === Number(e.target.value)); if (p) setAmount(String(p.price)) }}><option value="">Select</option>{plans?.map((p) => <option key={p.id} value={p.id}>{p.planName}</option>)}</select></div>
          <div><Label>Amount</Label><Input value={amount} onChange={(e) => setAmount(e.target.value)} /></div>
          <div className="flex items-end"><Button disabled={assign.isPending} onClick={() => assign.mutate()}>Assign</Button></div>
        </CardContent>
      </Card>
      <div className="space-y-2">
        {subs?.map((s) => (
          <div key={s.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border px-4 py-3 text-sm">
            <div><p className="font-medium">{s.planName}</p><p className="text-muted-foreground">{s.startDate} → {s.endDate} · {formatInr(s.paymentAmount)}</p></div>
            <div className="flex gap-2">
              <Button size="sm" variant="outline" onClick={() => extend.mutate(s.id)}>Extend 3mo</Button>
              <Button size="sm" variant="destructive" onClick={() => cancel.mutate(s.id)}>Cancel</Button>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
