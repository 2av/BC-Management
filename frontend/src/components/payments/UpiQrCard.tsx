import { useMemo } from 'react'
import { QrCode } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export const PAYMENT_BRAND = 'Mitra Niidhi Samooh'

export type PaymentMethods = {
  qrEnabled: boolean
  upiId: string
  payeeName: string
  paymentNote: string
  qrImageUrl: string | null
  upiUrl: string | null
}

/** Builds UPI deep-link + QR image URL with remark (`tn`) embedded in the QR. */
export function buildUpiQr(opts: {
  upiId: string
  payee?: string
  note?: string
  amount?: number | null
}) {
  const payee = opts.payee?.trim() || PAYMENT_BRAND
  const note = opts.note?.trim() || PAYMENT_BRAND
  const am = opts.amount != null && opts.amount > 0 ? `&am=${Number(opts.amount).toFixed(2)}` : ''
  const upiUrl =
    `upi://pay?pa=${encodeURIComponent(opts.upiId.trim())}` +
    `&pn=${encodeURIComponent(payee)}` +
    am +
    `&cu=INR&tn=${encodeURIComponent(note)}`
  const qrImageUrl = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(upiUrl)}`
  return { upiUrl, qrImageUrl, payee, note }
}

export function UpiQrCard({
  methods,
  amountLabel,
  amount,
  title = PAYMENT_BRAND,
  paymentText,
  compact = false,
}: {
  methods?: PaymentMethods | null
  amountLabel?: string
  /** Numeric amount encoded into the QR (preferred over parsing the label). */
  amount?: number | null
  /** Shown as card title — defaults to Mitra Niidhi Samooh */
  title?: string
  /** Remark / payment note — also encoded into the QR (`tn`) */
  paymentText?: string
  compact?: boolean
}) {
  const { t } = useTranslation()
  const note = paymentText || methods?.paymentNote || PAYMENT_BRAND
  const payee = methods?.payeeName || PAYMENT_BRAND

  const embedded = useMemo(() => {
    if (!methods?.upiId?.trim()) return null
    return buildUpiQr({
      upiId: methods.upiId,
      payee,
      note,
      amount,
    })
  }, [methods?.upiId, payee, note, amount])

  const qrImageUrl = embedded?.qrImageUrl || methods?.qrImageUrl
  const upiUrl = embedded?.upiUrl || methods?.upiUrl

  if (!methods?.qrEnabled || !qrImageUrl) {
    return (
      <Card className={compact ? 'border-dashed' : 'mb-6 border-dashed'}>
        <CardHeader className={compact ? 'p-4' : undefined}>
          <CardTitle className="flex items-center gap-2 text-base">
            <QrCode className="h-4 w-4" />
            {title}
          </CardTitle>
          <CardDescription>{t('upi.notConfigured')}</CardDescription>
        </CardHeader>
      </Card>
    )
  }

  return (
    <Card
      className={`${compact ? '' : 'mb-6'} border-teal-200 bg-gradient-to-br from-teal-50/80 to-card`}
    >
      <CardHeader className={compact ? 'p-4 pb-2' : undefined}>
        <CardTitle className="flex items-center gap-2 text-base">
          <QrCode className="h-4 w-4 text-primary" />
          {title}
        </CardTitle>
        <CardDescription>
          {t('member.scanQr')}
          {amountLabel ? ` · ${amountLabel}` : ''}
        </CardDescription>
      </CardHeader>
      <CardContent
        className={`flex flex-col items-center gap-4 sm:flex-row sm:items-start ${compact ? 'p-4 pt-0' : ''}`}
      >
        <img
          src={qrImageUrl}
          alt={`${title} UPI QR`}
          className="h-48 w-48 rounded-lg border bg-white p-2"
        />
        <div className="w-full space-y-2 text-sm">
          <p>
            <span className="text-muted-foreground">{t('member.payTo')}:</span>{' '}
            <strong>{payee}</strong>
          </p>
          <p>
            <span className="text-muted-foreground">UPI ID:</span> <strong>{methods.upiId}</strong>
          </p>
          <div className="rounded-lg border border-teal-200 bg-white/80 px-3 py-2">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
              {t('member.paymentRemark')}
            </p>
            <p className="mt-0.5 font-semibold text-foreground">{note}</p>
            <p className="mt-1 text-xs text-muted-foreground">{t('member.paymentRemarkHint')}</p>
          </div>
          {amountLabel ? (
            <p>
              <span className="text-muted-foreground">{t('common.amount')}:</span>{' '}
              <strong>{amountLabel}</strong>
            </p>
          ) : null}
          {upiUrl ? (
            <Button asChild size="sm" variant="outline">
              <a href={upiUrl} target="_blank" rel="noreferrer">
                {t('member.openUpiApp')}
              </a>
            </Button>
          ) : null}
        </div>
      </CardContent>
    </Card>
  )
}
