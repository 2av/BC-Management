import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

type Profile = {
  id: number
  memberName: string
  username: string | null
  phone: string | null
  email: string | null
  address: string | null
  status: string
  createdAt: string
}

export function MemberProfilePage() {
  const { t } = useTranslation()
  const api = useApi()
  const qc = useQueryClient()
  const [message, setMessage] = useState<string | null>(null)
  const { data } = useQuery({ queryKey: ['member-profile'], queryFn: () => api.get<Profile>('/api/members/me/profile') })
  const [form, setForm] = useState<Partial<Profile>>({})

  const save = useMutation({
    mutationFn: () => api.patch('/api/members/me/profile', {
      memberName: form.memberName ?? data?.memberName,
      phone: form.phone ?? data?.phone,
      email: form.email ?? data?.email,
      address: form.address ?? data?.address,
    }),
    onSuccess: async () => {
      setMessage('Profile updated.')
      await qc.invalidateQueries({ queryKey: ['member-profile'] })
    },
  })

  if (!data) return <p className="text-sm text-muted-foreground">Loading…</p>
  const current = { ...data, ...form }

  return (
    <div>
      <PageHeader title={t('member.profileTitle')} description={t('pages.memberProfileDesc')} />
      {message ? <p className="mb-3 text-sm text-emerald-700">{message}</p> : null}
      <Card>
        <CardHeader><CardTitle>{current.memberName}</CardTitle></CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2">
          <div><Label>Full name</Label><Input value={current.memberName} onChange={(e) => setForm({ ...form, memberName: e.target.value })} /></div>
          <div><Label>Username</Label><Input value={current.username ?? ''} disabled /></div>
          <div><Label>Phone</Label><Input value={current.phone ?? ''} onChange={(e) => setForm({ ...form, phone: e.target.value })} /></div>
          <div><Label>Email</Label><Input value={current.email ?? ''} onChange={(e) => setForm({ ...form, email: e.target.value })} /></div>
          <div className="md:col-span-2"><Label>Address</Label><Input value={current.address ?? ''} onChange={(e) => setForm({ ...form, address: e.target.value })} /></div>
          <Button disabled={save.isPending} onClick={() => save.mutate()}>Save profile</Button>
        </CardContent>
      </Card>
    </div>
  )
}
