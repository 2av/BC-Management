import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'

type AuditRow = {
  id: number
  clientId: number | null
  clientName: string | null
  userType: string
  userId: number
  action: string
  tableName: string | null
  recordId: number | null
  ipAddress: string | null
  createdAt: string
}

export function SuperAdminAuditPage() {
  const { t } = useTranslation()
  const api = useApi()
  const { data, isLoading } = useQuery({
    queryKey: ['sa-audit'],
    queryFn: () => api.get<AuditRow[]>('/api/super-admin/audit-logs?page=1&pageSize=100'),
  })

  return (
    <div>
      <PageHeader title={t('superAdmin.auditTitle')} description={t('superAdmin.auditDesc')} />
      {isLoading ? <p className="text-sm text-muted-foreground">{t('common.loading')}</p> : null}
      <Card>
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full min-w-[800px] text-left text-sm">
              <thead className="bg-muted/60">
                <tr>
                  <th className="px-3 py-2.5 font-medium">When</th>
                  <th className="px-3 py-2.5 font-medium">Action</th>
                  <th className="px-3 py-2.5 font-medium">User</th>
                  <th className="px-3 py-2.5 font-medium">Client</th>
                  <th className="px-3 py-2.5 font-medium">Record</th>
                  <th className="px-3 py-2.5 font-medium">IP</th>
                </tr>
              </thead>
              <tbody>
                {data?.map((r) => (
                  <tr key={r.id} className="border-t">
                    <td className="px-3 py-2 text-muted-foreground">
                      {new Date(r.createdAt).toLocaleString()}
                    </td>
                    <td className="px-3 py-2">
                      <Badge variant="muted">{r.action}</Badge>
                    </td>
                    <td className="px-3 py-2">
                      {r.userType} #{r.userId}
                    </td>
                    <td className="px-3 py-2">{r.clientName ?? (r.clientId != null ? `#${r.clientId}` : '—')}</td>
                    <td className="px-3 py-2 text-muted-foreground">
                      {r.tableName ? `${r.tableName}${r.recordId != null ? ` #${r.recordId}` : ''}` : '—'}
                    </td>
                    <td className="px-3 py-2 text-muted-foreground">{r.ipAddress ?? '—'}</td>
                  </tr>
                ))}
                {data && data.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-3 py-8 text-center text-muted-foreground">
                      No audit entries yet.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
