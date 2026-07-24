import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState } from 'react'
import { X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { formatInr } from '@/features/groups/types'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { PAYMENT_BRAND, UpiQrCard, type PaymentMethods } from '@/components/payments/UpiQrCard'

type PaymentDetail = {
  paymentId: number | null
  groupId: number
  groupName: string
  monthNumber: number
  amount: number
  paymentStatus: string
  transactionId: string | null
  payeeName: string
  paymentNote: string
  qrEnabled: boolean
  upiId: string | null
  qrImageUrl: string | null
  upiUrl: string | null
}

type PaymentOption = {
  groupId: number
  groupName: string
  monthNumber: number
  amount: number
  status: string
}

export function PaymentQrModal({
  open,
  onClose,
  groupId,
  groupName,
  groupMemberId,
  initialMonth,
}: {
  open: boolean
  onClose: () => void
  groupId: number
  groupName: string
  groupMemberId?: number | null
  initialMonth?: number | null
}) {
  const { t } = useTranslation()
  const api = useApi()
  const qc = useQueryClient()
  const [month, setMonth] = useState<number | null>(initialMonth ?? null)
  const [utr, setUtr] = useState('')
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const paymentText = `${PAYMENT_BRAND} - ${groupName}`

  const { data: methods } = useQuery({
    queryKey: ['payment-methods'],
    queryFn: () => api.get<PaymentMethods>('/api/members/me/payment-methods'),
    enabled: open,
  })

  const { data: options } = useQuery({
    queryKey: ['payment-options'],
    queryFn: () => api.get<PaymentOption[]>('/api/members/me/payment-options'),
    enabled: open,
  })

  const groupOptions = useMemo(
    () =>
      (options ?? [])
        .filter((o) => o.groupId === groupId && o.status !== 'paid')
        .sort((a, b) => a.monthNumber - b.monthNumber),
    [options, groupId],
  )

  const activeMonth =
    month ??
    initialMonth ??
    groupOptions[0]?.monthNumber ??
    null

  useEffect(() => {
    if (!open) return
    setMessage(null)
    setError(null)
    setUtr('')
    setMonth(initialMonth ?? null)
  }, [open, groupId, initialMonth])

  useEffect(() => {
    if (!open || month != null) return
    if (groupOptions[0]) setMonth(groupOptions[0].monthNumber)
  }, [open, month, groupOptions])

  const { data: detail, isLoading: detailLoading } = useQuery({
    queryKey: ['payment-detail', groupId, activeMonth],
    queryFn: () => api.get<PaymentDetail>(`/api/members/me/payments/${groupId}/${activeMonth}`),
    enabled: open && !!activeMonth,
  })

  const qrMethods: PaymentMethods | null = useMemo(() => {
    if (detail?.qrImageUrl && detail.upiId) {
      return {
        qrEnabled: detail.qrEnabled || detail.paymentStatus !== 'paid',
        upiId: detail.upiId,
        payeeName: detail.payeeName || PAYMENT_BRAND,
        paymentNote: detail.paymentNote || paymentText,
        qrImageUrl: detail.qrImageUrl,
        upiUrl: detail.upiUrl,
      }
    }
    if (methods?.qrEnabled) {
      return {
        ...methods,
        payeeName: PAYMENT_BRAND,
        paymentNote: paymentText,
      }
    }
    return methods ?? null
  }, [detail, methods, paymentText])

  useEffect(() => {
    if (detail?.transactionId) setUtr(detail.transactionId)
  }, [detail?.transactionId])

  const submitUtr = useMutation({
    mutationFn: () =>
      api.post<{ message?: string }>(`/api/members/me/payments/${groupId}/${activeMonth}/utr`, {
        transactionId: utr.trim(),
        groupMemberId: groupMemberId ?? null,
      }),
    onSuccess: async (res) => {
      setMessage(res.message ?? t('member.utrSubmitted'))
      setError(null)
      await qc.invalidateQueries({ queryKey: ['payment-detail', groupId, activeMonth] })
      await qc.invalidateQueries({ queryKey: ['my-payments'] })
      await qc.invalidateQueries({ queryKey: ['member-dashboard'] })
      await qc.invalidateQueries({ queryKey: ['payment-options'] })
    },
    onError: (e: Error) => setError(e.message),
  })

  if (!open) return null

  const amountLabel =
    detail && detail.paymentStatus !== 'paid' ? formatInr(detail.amount) : undefined

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-navy/50 p-4 backdrop-blur-sm">
      <div className="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-card shadow-xl">
        <div className="sticky top-0 z-10 flex items-start justify-between gap-3 border-b border-border bg-card px-5 py-4">
          <div>
            <h2 className="font-display text-lg text-navy">{PAYMENT_BRAND}</h2>
            <p className="text-sm text-muted-foreground">
              {t('member.payForGroup', { group: groupName })}
            </p>
          </div>
          <Button type="button" size="sm" variant="ghost" className="px-2" onClick={onClose} aria-label="Close">
            <X className="h-4 w-4" />
          </Button>
        </div>

        <div className="space-y-4 p-5">
          <ol className="list-decimal space-y-1 rounded-xl border border-border bg-muted/40 px-5 py-3 text-sm text-muted-foreground">
            <li>{t('member.payStepScan')}</li>
            <li>{t('member.payStepRemark', { remark: paymentText })}</li>
            <li>{t('member.payStepUtr')}</li>
          </ol>

          {groupOptions.length > 1 ? (
            <div className="space-y-1.5">
              <Label htmlFor="pay-month">{t('common.month')}</Label>
              <select
                id="pay-month"
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                value={activeMonth ?? ''}
                onChange={(e) => setMonth(Number(e.target.value))}
              >
                {groupOptions.map((o) => (
                  <option key={o.monthNumber} value={o.monthNumber}>
                    {t('common.month')} {o.monthNumber} · {formatInr(o.amount)} ({o.status})
                  </option>
                ))}
              </select>
            </div>
          ) : activeMonth ? (
            <p className="text-sm text-muted-foreground">
              {t('common.month')} {activeMonth}
              {detail ? ` · ${formatInr(detail.amount)}` : ''}
            </p>
          ) : null}

          {detailLoading ? <p className="text-sm text-muted-foreground">{t('common.loading')}</p> : null}

          <UpiQrCard
            compact
            methods={qrMethods}
            title={PAYMENT_BRAND}
            paymentText={detail?.paymentNote || paymentText}
            amount={detail && detail.paymentStatus !== 'paid' ? detail.amount : null}
            amountLabel={amountLabel}
          />

          {detail?.paymentStatus === 'paid' ? (
            <p className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
              {t('member.alreadyPaid')}
            </p>
          ) : (
            <div className="space-y-2 rounded-xl border border-border p-3">
              <Label htmlFor="utr-input">{t('member.utrLabel')}</Label>
              <Input
                id="utr-input"
                placeholder={t('member.utrPlaceholder')}
                value={utr}
                onChange={(e) => setUtr(e.target.value)}
                autoComplete="off"
              />
              <p className="text-xs text-muted-foreground">{t('member.utrHint')}</p>
              <Button
                className="w-full"
                disabled={submitUtr.isPending || utr.trim().length < 6 || !activeMonth}
                onClick={() => submitUtr.mutate()}
              >
                {submitUtr.isPending ? t('common.saving') : t('member.submitUtr')}
              </Button>
            </div>
          )}

          {message ? <p className="text-sm text-emerald-700">{message}</p> : null}
          {error ? <p className="text-sm text-destructive">{error}</p> : null}
        </div>
      </div>
    </div>
  )
}
