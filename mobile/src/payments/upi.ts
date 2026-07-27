export const PAYMENT_BRAND = 'Mitra Niidhi Samooh'

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
  const qrImageUrl = `https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=${encodeURIComponent(upiUrl)}`
  return { upiUrl, qrImageUrl, payee, note }
}
