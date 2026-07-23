import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

type Config = { upiId: string; bankAccountName: string; paymentNote: string; qrEnabled: boolean }

export function AdminPaymentConfigPage() {
  const { t } = useTranslation()
  const api = useApi()
  const qc = useQueryClient()
  const [message, setMessage] = useState<string | null>(null)
  const { data } = useQuery({ queryKey: ['payment-config'], queryFn: () => api.get<Config>('/api/settings/payment-config') })
  const [form, setForm] = useState<Config | null>(null)
  const current = form ?? data

  const save = useMutation({
    mutationFn: () => api.put('/api/settings/payment-config', current),
    onSuccess: async () => {
      setMessage('Payment configuration saved.')
      await qc.invalidateQueries({ queryKey: ['payment-config'] })
    },
  })

  if (!current) return <p className="text-sm text-muted-foreground">Loading…</p>

  return (
    <div>
      <PageHeader title={t('admin.paymentConfigTitle')} description={t('pages.paymentConfigDesc')} />
      {message ? <p className="mb-3 text-sm text-emerald-700">{message}</p> : null}
      <Card>
        <CardHeader><CardTitle>UPI / QR settings</CardTitle></CardHeader>
        <CardContent className="grid gap-4 md:grid-cols-2">
          <div><Label>UPI ID</Label><Input value={current.upiId} onChange={(e) => setForm({ ...current, upiId: e.target.value })} /></div>
          <div><Label>Payee name</Label><Input value={current.bankAccountName} onChange={(e) => setForm({ ...current, bankAccountName: e.target.value })} /></div>
          <div className="md:col-span-2"><Label>Payment note</Label><Input value={current.paymentNote} onChange={(e) => setForm({ ...current, paymentNote: e.target.value })} /></div>
          <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={current.qrEnabled} onChange={(e) => setForm({ ...current, qrEnabled: e.target.checked })} /> Enable QR payments</label>
          <Button disabled={save.isPending} onClick={() => save.mutate()}>Save configuration</Button>
        </CardContent>
      </Card>
    </div>
  )
}
