import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

type Notification = { id: number; title: string; message: string; type: string; isRead: boolean; createdAt: string }
type Counts = { total: number; unread: number; warning: number; danger: number }

const filters = ['all', 'unread', 'read', 'warning', 'danger'] as const

export function NotificationsInboxPage({
  titleKey = 'admin.notificationsTitle',
  descriptionKey,
}: {
  titleKey?: string
  descriptionKey?: string
}) {
  const { t } = useTranslation()
  const api = useApi()
  const qc = useQueryClient()
  const [filter, setFilter] = useState<(typeof filters)[number]>('all')
  const { data: counts } = useQuery({
    queryKey: ['notif-counts'],
    queryFn: () => api.get<Counts>('/api/notifications/counts'),
  })
  const { data } = useQuery({
    queryKey: ['notifications', filter],
    queryFn: () => api.get<Notification[]>(`/api/notifications?filter=${filter}`),
  })

  const markRead = useMutation({
    mutationFn: (id: number) => api.patch(`/api/notifications/${id}/read`),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['notifications'] })
      void qc.invalidateQueries({ queryKey: ['notif-counts'] })
    },
  })
  const markAll = useMutation({
    mutationFn: () => api.post('/api/notifications/mark-all-read'),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['notifications'] })
      void qc.invalidateQueries({ queryKey: ['notif-counts'] })
    },
  })
  const del = useMutation({
    mutationFn: (id: number) => api.delete(`/api/notifications/${id}`),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ['notifications'] }),
  })

  const filterLabel = (f: (typeof filters)[number]) => {
    if (f === 'all') return t('common.all')
    if (f === 'unread') return t('common.unread', { count: counts?.unread ?? 0 }).replace(/^\d+\s*/, '') || t('common.pending')
    if (f === 'read') return t('common.read')
    if (f === 'warning') return t('common.warning')
    return t('common.danger')
  }

  return (
    <div>
      <PageHeader
        title={t(titleKey)}
        description={descriptionKey ? t(descriptionKey) : t('common.unread', { count: counts?.unread ?? 0 })}
        actions={
          <Button variant="outline" onClick={() => markAll.mutate()}>
            {t('common.markAllRead')}
          </Button>
        }
      />
      <div className="mb-4 flex flex-wrap gap-2">
        {filters.map((f) => (
          <Button
            key={f}
            size="sm"
            variant={filter === f ? 'default' : 'outline'}
            onClick={() => setFilter(f)}
          >
            {filterLabel(f)}
          </Button>
        ))}
      </div>
      <div className="space-y-2">
        {data?.map((n) => (
          <div key={n.id} className={`rounded-lg border px-4 py-3 ${n.isRead ? 'opacity-75' : 'bg-card'}`}>
            <div className="flex items-start justify-between gap-2">
              <div>
                <div className="flex items-center gap-2">
                  <p className="font-medium">{n.title}</p>
                  <Badge variant={n.type === 'danger' || n.type === 'warning' ? 'warning' : 'muted'}>{n.type}</Badge>
                </div>
                <p className="text-sm text-muted-foreground">{n.message}</p>
                <p className="mt-1 text-xs text-muted-foreground">
                  {new Date(n.createdAt).toLocaleString()}
                </p>
              </div>
              <div className="flex gap-2">
                {!n.isRead ? (
                  <Button size="sm" variant="outline" onClick={() => markRead.mutate(n.id)}>
                    {t('common.markRead')}
                  </Button>
                ) : null}
                <Button size="sm" variant="ghost" onClick={() => del.mutate(n.id)}>
                  {t('common.delete')}
                </Button>
              </div>
            </div>
          </div>
        ))}
        {data && data.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('common.loading').replace('…', '')}—</p>
        ) : null}
      </div>
    </div>
  )
}
