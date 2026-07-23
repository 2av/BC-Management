import { useQuery } from '@tanstack/react-query'
import { Link, useLocation, useParams } from 'react-router-dom'
import { Printer } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/features/auth/AuthContext'
import { useApi } from '@/shared/api/client'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { formatInr } from '@/features/groups/types'

type Invoice = {
  invoiceNumber: string
  invoiceDate: string
  groupName: string
  memberName: string
  memberNumber: number
  phone: string | null
  email: string | null
  lines: { monthNumber: number; expectedAmount: number; paidAmount: number; status: string; paymentDate: string | null }[]
  totalPaid: number
  givenAmount: number
  profit: number
}

export function MemberInvoicePage() {
  const { t } = useTranslation()
  const { groupId, memberId: memberIdParam } = useParams()
  const location = useLocation()
  const { user } = useAuth()
  const api = useApi()
  const gid = Number(groupId)
  const memberId = Number(memberIdParam) || user?.id || 0
  const isAdmin = location.pathname.startsWith('/admin')
  const backTo = isAdmin ? `/admin/groups/${gid}` : `/member/groups/${gid}`

  const { data } = useQuery({
    queryKey: ['invoice', gid, memberId],
    queryFn: () => api.get<Invoice>(`/api/groups/${gid}/members/${memberId}/invoice`),
    enabled: gid > 0 && memberId > 0,
  })

  return (
    <div className="print:p-0">
      <div className="mb-4 flex items-center justify-between print:hidden">
        <Button asChild variant="ghost">
          <Link to={backTo}>← Back to ledger</Link>
        </Button>
        <Button onClick={() => window.print()}>
          <Printer className="h-4 w-4" /> Print
        </Button>
      </div>
      {data ? (
        <div className="rounded-xl border bg-card p-6">
          <PageHeader title={t('member.invoiceTitle')} description={data.invoiceNumber} />
          <div className="mb-6 grid gap-4 text-sm md:grid-cols-2">
            <div>
              <p className="font-medium">{data.groupName}</p>
              <p className="text-muted-foreground">Group ledger invoice</p>
            </div>
            <div className="text-right">
              <p className="font-medium">
                {data.memberName} #{data.memberNumber}
              </p>
              <p className="text-muted-foreground">
                {data.phone} · {data.email}
              </p>
              <p>Date: {data.invoiceDate}</p>
            </div>
          </div>
          <table className="mb-6 w-full text-left text-sm">
            <thead className="border-b">
              <tr>
                <th className="py-2">Month</th>
                <th>Expected</th>
                <th>Paid</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {data.lines.map((l) => (
                <tr key={l.monthNumber} className="border-b">
                  <td className="py-2">{l.monthNumber}</td>
                  <td>{formatInr(l.expectedAmount)}</td>
                  <td>{formatInr(l.paidAmount)}</td>
                  <td className="capitalize">{l.status.replace('_', ' ')}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <div className="grid gap-2 text-sm md:grid-cols-3">
            <p>
              Total paid: <strong>{formatInr(data.totalPaid)}</strong>
            </p>
            <p>
              Amount received: <strong>{formatInr(data.givenAmount)}</strong>
            </p>
            <p>
              Net profit:{' '}
              <strong className={data.profit >= 0 ? 'text-emerald-700' : 'text-destructive'}>
                {formatInr(data.profit)}
              </strong>
            </p>
          </div>
        </div>
      ) : (
        <p className="text-sm text-muted-foreground">Loading invoice…</p>
      )}
    </div>
  )
}
