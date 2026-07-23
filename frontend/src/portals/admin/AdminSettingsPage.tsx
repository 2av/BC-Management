import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Database, RefreshCw, Wrench } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { PageHeader } from '@/components/layout/PageHeader'
import { StatCard } from '@/components/layout/StatCard'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { formatInr } from '@/features/groups/types'

type Setting = { key: string; value: string; type: string; category: string; description: string | null }
type Response = {
  settingsByCategory: Record<string, Setting[]>
  stats: { totalGroups: number; totalMembers: number; totalCollected: number; totalPayments: number; totalAdmins: number }
}

type SchemaIssue = { kind: string; objectName: string; detail: string; canAutoFix: boolean }
type SchemaCheck = { isUpToDate: boolean; issues: SchemaIssue[]; notes: string[] }
type SchemaMigrate = {
  succeeded: boolean
  appliedCount: number
  applied: string[]
  skipped: string[]
  errors: string[]
}

export function AdminSettingsPage() {
  const { t } = useTranslation()
  const api = useApi()
  const qc = useQueryClient()
  const [values, setValues] = useState<Record<string, string>>({})
  const [migrateResult, setMigrateResult] = useState<SchemaMigrate | null>(null)

  const { data } = useQuery({ queryKey: ['system-settings'], queryFn: () => api.get<Response>('/api/settings') })
  const {
    data: schema,
    isFetching: schemaLoading,
    refetch: checkSchema,
  } = useQuery({
    queryKey: ['schema-check'],
    queryFn: () => api.get<SchemaCheck>('/api/settings/schema'),
  })

  const save = useMutation({
    mutationFn: () => {
      const merged: Record<string, string> = { ...values }
      if (data) {
        for (const cat of Object.values(data.settingsByCategory)) {
          for (const s of cat) {
            if (!(s.key in merged)) merged[s.key] = s.value
          }
        }
      }
      return api.put('/api/settings', { settings: merged })
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['system-settings'] }),
  })

  const migrate = useMutation({
    mutationFn: () => api.post<SchemaMigrate>('/api/settings/schema/migrate'),
    onSuccess: async (res) => {
      setMigrateResult(res)
      await qc.invalidateQueries({ queryKey: ['schema-check'] })
      await qc.invalidateQueries({ queryKey: ['system-settings'] })
    },
  })

  const getVal = (s: Setting) => values[s.key] ?? s.value

  return (
    <div>
      <PageHeader title={t('admin.settingsTitle')} description={t('pages.settingsDesc')} />

      <Card className="mb-6 border-teal-200 bg-gradient-to-br from-teal-50/80 to-card">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Database className="h-5 w-5 text-primary" />
            Database schema
          </CardTitle>
          <CardDescription>
            Check for missing tables/columns used by subscriptions, payments, notifications, and settings — then apply
            safe auto-fixes.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-center gap-2">
            <Button type="button" variant="outline" disabled={schemaLoading} onClick={() => void checkSchema()}>
              <RefreshCw className={`h-4 w-4 ${schemaLoading ? 'animate-spin' : ''}`} />
              Check schema
            </Button>
            <Button
              type="button"
              disabled={migrate.isPending}
              onClick={() => {
                setMigrateResult(null)
                migrate.mutate()
              }}
            >
              <Wrench className="h-4 w-4" />
              {migrate.isPending ? 'Migrating…' : 'Migrate missing tables / columns'}
            </Button>
            {schema ? (
              <Badge variant={schema.isUpToDate ? 'success' : 'warning'}>
                {schema.isUpToDate ? 'Up to date' : `${schema.issues.length} issue(s)`}
              </Badge>
            ) : null}
          </div>

          {schema?.notes?.length ? (
            <ul className="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
              {schema.notes.map((n) => (
                <li key={n}>{n}</li>
              ))}
            </ul>
          ) : null}

          {schema && !schema.isUpToDate ? (
            <div className="overflow-x-auto rounded-lg border">
              <table className="w-full text-left text-sm">
                <thead className="bg-muted">
                  <tr>
                    <th className="px-3 py-2">Kind</th>
                    <th className="px-3 py-2">Object</th>
                    <th className="px-3 py-2">Detail</th>
                  </tr>
                </thead>
                <tbody>
                  {schema.issues.map((issue) => (
                    <tr key={issue.objectName} className="border-t">
                      <td className="px-3 py-2 capitalize">{issue.kind}</td>
                      <td className="px-3 py-2 font-mono text-xs">{issue.objectName}</td>
                      <td className="px-3 py-2">{issue.detail}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : null}

          {migrate.isError ? (
            <p className="text-sm text-destructive">{(migrate.error as Error).message}</p>
          ) : null}

          {migrateResult ? (
            <div className="space-y-2 rounded-lg border border-border bg-card p-3 text-sm">
              <p className={migrateResult.succeeded ? 'font-medium text-emerald-700' : 'font-medium text-amber-700'}>
                {migrateResult.succeeded
                  ? `Migration finished — ${migrateResult.appliedCount} change(s) applied.`
                  : `Migration finished with errors — ${migrateResult.appliedCount} applied, ${migrateResult.errors.length} failed.`}
              </p>
              {migrateResult.applied.length ? (
                <ul className="list-disc pl-5 text-emerald-800">
                  {migrateResult.applied.map((a) => (
                    <li key={a}>{a}</li>
                  ))}
                </ul>
              ) : null}
              {migrateResult.errors.length ? (
                <ul className="list-disc pl-5 text-destructive">
                  {migrateResult.errors.map((e) => (
                    <li key={e}>{e}</li>
                  ))}
                </ul>
              ) : null}
            </div>
          ) : null}
        </CardContent>
      </Card>

      {data ? (
        <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard label="Groups" value={`${data.stats.totalGroups}`} />
          <StatCard label="Members" value={`${data.stats.totalMembers}`} />
          <StatCard label="Collected" value={formatInr(data.stats.totalCollected)} />
          <StatCard label="Admins" value={`${data.stats.totalAdmins}`} />
        </div>
      ) : null}
      {data
        ? Object.entries(data.settingsByCategory).map(([cat, settings]) => (
            <Card key={cat} className="mb-4">
              <CardHeader>
                <CardTitle className="capitalize">{cat}</CardTitle>
              </CardHeader>
              <CardContent className="grid gap-3 md:grid-cols-2">
                {settings.map((s) => (
                  <div key={s.key} className="space-y-1">
                    <Label>{s.description ?? s.key}</Label>
                    {s.type === 'boolean' ? (
                      <label className="flex items-center gap-2 text-sm">
                        <input
                          type="checkbox"
                          checked={getVal(s) === '1'}
                          onChange={(e) => setValues({ ...values, [s.key]: e.target.checked ? '1' : '0' })}
                        />
                        Enabled
                      </label>
                    ) : (
                      <Input value={getVal(s)} onChange={(e) => setValues({ ...values, [s.key]: e.target.value })} />
                    )}
                  </div>
                ))}
              </CardContent>
            </Card>
          ))
        : null}
      <Button disabled={save.isPending} onClick={() => save.mutate()}>
        Save all settings
      </Button>
    </div>
  )
}
