import { useEffect, useRef, useState } from 'react'
import { Dices, Play, Redo2, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

const WHEEL_COLORS = [
  '#FF6B6B',
  '#4ECDC4',
  '#45B7D1',
  '#96CEB4',
  '#FFEAA7',
  '#DDA0DD',
  '#98D8C8',
  '#F7DC6F',
  '#BB8FCE',
  '#85C1E9',
  '#F8B500',
  '#00CED1',
  '#FF69B4',
  '#32CD32',
  '#FF8C00',
]

export type SpinMember = {
  memberId: number
  groupMemberId?: number
  memberName: string
  memberNumber: number
  handLabel?: string | null
}

type SpinWheelModalProps = {
  open: boolean
  members: SpinMember[]
  monthNumber: number
  /** If set (like PHP saved/custom pick), spin always lands on this member */
  predeterminedMemberId?: number | null
  predeterminedGroupMemberId?: number | null
  onClose: () => void
  onConfirm: (member: SpinMember) => void
  confirming?: boolean
}

export function SpinWheelModal({
  open,
  members,
  monthNumber,
  predeterminedMemberId = null,
  predeterminedGroupMemberId = null,
  onClose,
  onConfirm,
  confirming = false,
}: SpinWheelModalProps) {
  const canvasRef = useRef<HTMLCanvasElement>(null)
  const rotationRef = useRef(0)
  const [isSpinning, setIsSpinning] = useState(false)
  const [winner, setWinner] = useState<SpinMember | null>(null)
  const [showConfirm, setShowConfirm] = useState(false)

  const predetermined = predeterminedGroupMemberId
    ? members.find((m) => m.groupMemberId === predeterminedGroupMemberId) ?? null
    : predeterminedMemberId
      ? members.find((m) => m.memberId === predeterminedMemberId) ?? null
      : null

  function drawWheel(rotation: number) {
    const canvas = canvasRef.current
    if (!canvas || members.length === 0) return
    const ctx = canvas.getContext('2d')
    if (!ctx) return

    const size = canvas.width
    const center = size / 2
    const radius = center - 4
    const sliceAngle = (2 * Math.PI) / members.length

    ctx.clearRect(0, 0, size, size)
    ctx.save()
    ctx.translate(center, center)
    ctx.rotate(rotation)

    members.forEach((member, i) => {
      const startAngle = i * sliceAngle
      const endAngle = startAngle + sliceAngle

      ctx.beginPath()
      ctx.moveTo(0, 0)
      ctx.arc(0, 0, radius, startAngle, endAngle)
      ctx.closePath()
      ctx.fillStyle = WHEEL_COLORS[i % WHEEL_COLORS.length]
      ctx.fill()
      ctx.strokeStyle = '#fff'
      ctx.lineWidth = 2
      ctx.stroke()

      ctx.save()
      ctx.rotate(startAngle + sliceAngle / 2)
      ctx.textAlign = 'right'
      ctx.fillStyle = '#fff'
      ctx.font = 'bold 12px Arial, sans-serif'
      ctx.shadowColor = 'rgba(0,0,0,0.5)'
      ctx.shadowBlur = 3
      let displayName = member.handLabel
        ? `${member.memberName} · ${member.handLabel}`
        : member.memberName
      if (displayName.length > 12) displayName = `${displayName.slice(0, 11)}…`
      ctx.fillText(displayName, radius - 14, 5)
      ctx.restore()
    })

    ctx.restore()

    ctx.beginPath()
    ctx.arc(center, center, 24, 0, 2 * Math.PI)
    ctx.fillStyle = '#fff'
    ctx.fill()
    ctx.strokeStyle = '#333'
    ctx.lineWidth = 2
    ctx.stroke()
  }

  useEffect(() => {
    if (!open) return
    rotationRef.current = 0
    setWinner(null)
    setShowConfirm(false)
    setIsSpinning(false)
    const id = requestAnimationFrame(() => drawWheel(0))
    return () => cancelAnimationFrame(id)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, members])

  function resolveWinnerIndex() {
    if (predeterminedGroupMemberId) {
      const index = members.findIndex((m) => m.groupMemberId === predeterminedGroupMemberId)
      if (index !== -1) return index
    }
    if (predeterminedMemberId) {
      const index = members.findIndex((m) => m.memberId === predeterminedMemberId)
      if (index !== -1) return index
    }
    return Math.floor(Math.random() * members.length)
  }

  function spin() {
    if (isSpinning || members.length === 0) return

    setIsSpinning(true)
    setWinner(null)

    const winnerIndex = resolveWinnerIndex()
    const sliceAngle = (2 * Math.PI) / members.length
    const extraSpins = 5 + Math.floor(Math.random() * 3)
    const targetAngle =
      extraSpins * 2 * Math.PI + (3 * Math.PI) / 2 - winnerIndex * sliceAngle - sliceAngle / 2
    const startRotation = rotationRef.current
    const totalRotation = targetAngle - (startRotation % (2 * Math.PI))
    const duration = 4000
    const startTime = performance.now()

    function animate(now: number) {
      const elapsed = now - startTime
      const progress = Math.min(elapsed / duration, 1)
      const easeOut = 1 - Math.pow(1 - progress, 4)
      rotationRef.current = startRotation + totalRotation * easeOut
      drawWheel(rotationRef.current)

      if (progress < 1) {
        requestAnimationFrame(animate)
      } else {
        setIsSpinning(false)
        setWinner(members[winnerIndex])
      }
    }

    requestAnimationFrame(animate)
  }

  if (!open) return null

  const winnerLabel = winner
    ? `${winner.memberName}${winner.memberNumber ? ` (#${winner.memberNumber})` : ''}`
    : ''

  const subtitle = predetermined
    ? `Spin the wheel for Month ${monthNumber} — result will be: ${predetermined.memberName}`
    : `Spin the wheel to randomly pick a pending member for Month ${monthNumber}.`

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-navy/50 p-4 backdrop-blur-sm">
      <div className="relative w-full max-w-lg overflow-hidden rounded-2xl bg-card shadow-xl">
        <div className="flex items-center justify-between border-b border-border bg-amber-50 px-5 py-4">
          <div>
            <h2 className="flex items-center gap-2 font-display text-lg text-navy">
              <Dices className="h-5 w-5 text-amber-600" />
              Spin the wheel
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">{subtitle}</p>
          </div>
          <Button type="button" size="sm" variant="ghost" onClick={onClose} disabled={isSpinning || confirming}>
            <X className="h-4 w-4" />
          </Button>
        </div>

        <div className="px-5 py-6">
          {members.length === 0 ? (
            <p className="py-10 text-center text-sm text-muted-foreground">
              No eligible members left for random pick.
            </p>
          ) : (
            <>
              <div className="relative mx-auto h-[280px] w-[280px] sm:h-[320px] sm:w-[320px]">
                <div
                  className="absolute -top-2 left-1/2 z-10 -translate-x-1/2"
                  style={{
                    width: 0,
                    height: 0,
                    borderLeft: '14px solid transparent',
                    borderRight: '14px solid transparent',
                    borderTop: '28px solid #dc3545',
                    filter: 'drop-shadow(0 2px 4px rgba(0,0,0,0.3))',
                  }}
                />
                <canvas
                  ref={canvasRef}
                  width={320}
                  height={320}
                  className="h-full w-full rounded-full shadow-[0_8px_24px_rgba(0,0,0,0.2)]"
                />
                <div className="pointer-events-none absolute left-1/2 top-1/2 z-[5] h-12 w-12 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-[#333] bg-gradient-to-br from-white to-[#f0f0f0] shadow-md" />
              </div>

              <div
                className={cn(
                  'mt-4 hidden rounded-[10px] bg-gradient-to-br from-emerald-600 to-teal-500 px-5 py-3 text-center text-white',
                  winner && 'block animate-in fade-in',
                )}
              >
                <p className="text-sm opacity-90">Winner</p>
                <p className="text-xl font-bold">{winnerLabel}</p>
              </div>

              <div className="mt-5 flex flex-wrap justify-center gap-2">
                <Button
                  type="button"
                  size="lg"
                  className="bg-amber-500 text-white hover:bg-amber-600"
                  disabled={isSpinning || confirming}
                  onClick={spin}
                >
                  {isSpinning ? (
                    'Spinning…'
                  ) : winner ? (
                    <>
                      <Redo2 className="h-4 w-4" />
                      SPIN AGAIN
                    </>
                  ) : (
                    <>
                      <Play className="h-4 w-4" />
                      SPIN
                    </>
                  )}
                </Button>
                {winner ? (
                  <Button
                    type="button"
                    size="lg"
                    className="bg-emerald-600 text-white hover:bg-emerald-700"
                    disabled={isSpinning || confirming}
                    onClick={() => setShowConfirm(true)}
                  >
                    Confirm Pick
                  </Button>
                ) : null}
              </div>
            </>
          )}
        </div>
      </div>

      {showConfirm && winner ? (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-md rounded-2xl bg-card p-6 shadow-xl">
            <h3 className="font-display text-lg text-navy">Confirm Random Pick</h3>
            <p className="mt-2 text-sm text-muted-foreground">Confirm this member for Month {monthNumber}?</p>
            <div className="my-4 rounded-[10px] border-2 border-dashed border-emerald-500 bg-muted/40 px-4 py-4 text-center text-xl font-bold text-emerald-700">
              {winnerLabel}
            </div>
            <p className="mb-4 text-xs text-muted-foreground">This action cannot be undone.</p>
            <div className="flex justify-end gap-2">
              <Button type="button" variant="ghost" disabled={confirming} onClick={() => setShowConfirm(false)}>
                Cancel
              </Button>
              <Button
                type="button"
                className="bg-emerald-600 text-white hover:bg-emerald-700"
                disabled={confirming}
                onClick={() => onConfirm(winner)}
              >
                {confirming ? 'Saving…' : 'Yes, Confirm Pick'}
              </Button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  )
}
