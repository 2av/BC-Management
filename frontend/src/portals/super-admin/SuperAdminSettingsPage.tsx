import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

type Setting = { key: string; value: string; type: string; category: string; description: string | null }
type Response = {
  settingsByCategory: Record<string, Setting[]>
  stats: { totalGroups: number; totalMembers: number; totalCollected: number; totalPayments: number; totalAdmins: number }
}

export function SuperAdminSettingsPage() {
  const { t } = useTranslation()
  const api = useApi()
  const qc = useQueryClient()
  const [values, setValues] = useState<Record<string, string>>({})
  const [message, setMessage] = useState<string | null>(null)

  const { data } = useQuery({
    queryKey: ['system-settings'],
    queryFn: () => api.get<Response>('/api/settings'),
  })

  useEffect(() => {
    if (!data) return
    const next: Record<string, string> = {}
    Object.values(data.settingsByCategory).flat().forEach((s) => {
      next[s.key] = s.value
    })
    setValues(next)
  }, [data])

  const save = useMutation({
    mutationFn: () => api.put('/api/settings', { settings: values }),
    onSuccess: async () => {
      setMessage('Settings saved.')
      await qc.invalidateQueries({ queryKey: ['system-settings'] })
    },
    onError: (e: Error) => setMessage(e.message),
  })

  return (
    <div>
      <PageHeader
        title={t('superAdmin.settingsTitle')}
        description={t('superAdmin.settingsDesc')}
        actions={
          <Button disabled={save.isPending} onClick={() => save.mutate()}>
            {t('common.save')}
          </Button>
        }
      />
      {message ? <p className="mb-3 text-sm text-emerald-700">{message}</p> : null}
      <div className="grid gap-4">
        {data
          ? Object.entries(data.settingsByCategory).map(([category, items]) => (
              <Card key={category}>
                <CardHeader>
                  <CardTitle className="capitalize">{category}</CardTitle>
                  <CardDescription>{items.length} settings</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3 md:grid-cols-2">
                  {items.map((s) => (
                    <div key={s.key} className="space-y-1.5">
                      <Label htmlFor={s.key}>{s.key}</Label>
                      {s.description ? (
                        <p className="text-xs text-muted-foreground">{s.description}</p>
                      ) : null}
                      {s.type === 'boolean' ? (
                        <select
                          id={s.key}
                          className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                          value={values[s.key] ?? s.value}
                          onChange={(e) => setValues({ ...values, [s.key]: e.target.value })}
                        >
                          <option value="1">{t('common.active')}</option>
                          <option value="0">{t('common.inactive')}</option>
                        </select>
                      ) : (
                        <Input
                          id={s.key}
                          value={values[s.key] ?? ''}
                          onChange={(e) => setValues({ ...values, [s.key]: e.target.value })}
                        />
                      )}
                    </div>
                  ))}
                </CardContent>
              </Card>
            ))
          : (
            <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
          )}
      </div>
    </div>
  )
}
