import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { formatInr } from '@/features/groups/types'

type Payment = {
  id: number
  clientId: number
  clientName: string
  amount: number
  paymentMethod: string | null
  paymentReference: string | null
  paymentStatus: string
  paymentDate: string | null
  createdAt: string
}

export function SuperAdminPaymentsPage() {
  const { t } = useTranslation()
  const api = useApi()
  const qc = useQueryClient()
  const { data } = useQuery({
    queryKey: ['sa-payments'],
    queryFn: () => api.get<Payment[]>('/api/super-admin/payments'),
  })

  const markComplete = useMutation({
    mutationFn: (id: number) => api.patch(`/api/super-admin/payments/${id}/complete`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['sa-payments'] }),
  })

  return (
    <div>
      <PageHeader title={t('superAdmin.paymentsTitle')} description={t('pages.platformPaymentsDesc')} />
      <div className="overflow-x-auto rounded-xl border">
        <table className="w-full text-left text-sm">
          <thead className="bg-muted"><tr><th className="px-3 py-2">Client</th><th className="px-3 py-2">Amount</th><th className="px-3 py-2">Status</th><th className="px-3 py-2">Reference</th><th className="px-3 py-2" /></tr></thead>
          <tbody>
            {data?.map((p) => (
              <tr key={p.id} className="border-t">
                <td className="px-3 py-2">{p.clientName}</td>
                <td className="px-3 py-2">{formatInr(p.amount)}</td>
                <td className="px-3 py-2"><Badge variant={p.paymentStatus === 'completed' ? 'success' : 'warning'}>{p.paymentStatus}</Badge></td>
                <td className="px-3 py-2">{p.paymentReference ?? '—'}</td>
                <td className="px-3 py-2">{p.paymentStatus !== 'completed' ? <Button size="sm" onClick={() => markComplete.mutate(p.id)}>Mark paid</Button> : null}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
