import { QrCode } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export type PaymentMethods = {
  qrEnabled: boolean
  upiId: string
  payeeName: string
  paymentNote: string
  qrImageUrl: string | null
  upiUrl: string | null
}

export function UpiQrCard({
  methods,
  amountLabel,
}: {
  methods?: PaymentMethods | null
  amountLabel?: string
}) {
  const { t } = useTranslation()

  if (!methods?.qrEnabled || !methods.qrImageUrl) {
    return (
      <Card className="mb-6 border-dashed">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <QrCode className="h-4 w-4" />
            {t('upi.title')}
          </CardTitle>
          <CardDescription>{t('upi.notConfigured')}</CardDescription>
        </CardHeader>
      </Card>
    )
  }

  return (
    <Card className="mb-6 border-teal-200 bg-gradient-to-br from-teal-50/80 to-card">
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <QrCode className="h-4 w-4 text-primary" />
          {t('upi.title')}
        </CardTitle>
        <CardDescription>
          {t('member.scanQr')}
          {amountLabel ? ` · ${amountLabel}` : ''}. {methods.payeeName}
        </CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
        <img src={methods.qrImageUrl} alt="UPI QR" className="h-48 w-48 rounded-lg border bg-white p-2" />
        <div className="space-y-2 text-sm">
          <p>
            <span className="text-muted-foreground">UPI ID:</span> <strong>{methods.upiId}</strong>
          </p>
          {methods.paymentNote ? <p className="text-muted-foreground">{methods.paymentNote}</p> : null}
          {methods.upiUrl ? (
            <Button asChild size="sm" variant="outline">
              <a href={methods.upiUrl} target="_blank" rel="noreferrer">
                {t('member.copyUpi')}
              </a>
            </Button>
          ) : null}
        </div>
      </CardContent>
    </Card>
  )
}
