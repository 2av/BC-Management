import { useEffect, useMemo, useRef, useState } from 'react'
import { Plus, Search, UserPlus, X } from 'lucide-react'
import type { MemberListItem } from '@/features/members/types'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'

export type MemberSlotValue =
  | { kind: 'existing'; memberId: number; memberName: string; username: string | null }
  | { kind: 'new'; memberName: string }
  | null

type MemberSlotsPickerProps = {
  slotCount: number
  members: MemberListItem[]
  value: MemberSlotValue[]
  onChange: (next: MemberSlotValue[]) => void
}

export function MemberSlotsPicker({ slotCount, members, value, onChange }: MemberSlotsPickerProps) {
  const slots = useMemo(() => {
    const next = value.slice(0, slotCount)
    while (next.length < slotCount) next.push(null)
    return next
  }, [value, slotCount])

  useEffect(() => {
    if (value.length !== slotCount) {
      const next = value.slice(0, slotCount)
      while (next.length < slotCount) next.push(null)
      onChange(next)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [slotCount])

  function setSlot(index: number, slot: MemberSlotValue) {
    const next = [...slots]
    next[index] = slot
    onChange(next)
  }

  const filled = slots.filter(Boolean).length

  return (
    <div className="space-y-3 sm:col-span-2 lg:col-span-3">
      <div className="flex flex-wrap items-end justify-between gap-2">
        <div>
          <Label>Members ({filled}/{slotCount})</Label>
          <p className="mt-0.5 text-xs text-muted-foreground">
            Search an existing member, or type a name and choose Create new.
          </p>
        </div>
      </div>
      <div className="grid gap-2">
        {slots.map((slot, index) => (
          <MemberSlotRow
            key={index}
            index={index}
            slot={slot}
            members={members}
            onSelect={(s) => setSlot(index, s)}
            onClear={() => setSlot(index, null)}
          />
        ))}
      </div>
    </div>
  )
}

function MemberSlotRow({
  index,
  slot,
  members,
  onSelect,
  onClear,
}: {
  index: number
  slot: MemberSlotValue
  members: MemberListItem[]
  onSelect: (slot: MemberSlotValue) => void
  onClear: () => void
}) {
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  const wrapRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    function onDoc(e: MouseEvent) {
      if (!wrapRef.current?.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onDoc)
    return () => document.removeEventListener('mousedown', onDoc)
  }, [])

  const q = query.trim().toLowerCase()
  const matches = useMemo(() => {
    if (!q) return members.slice(0, 8)
    return members
      .filter(
        (m) =>
          m.memberName.toLowerCase().includes(q) ||
          (m.username?.toLowerCase().includes(q) ?? false) ||
          (m.phone?.includes(q) ?? false),
      )
      .slice(0, 8)
  }, [members, q])

  const exactExisting = members.find(
    (m) => m.memberName.toLowerCase() === q || m.username?.toLowerCase() === q,
  )
  const canCreateNew = q.length >= 2 && !exactExisting

  if (slot) {
    return (
      <div className="flex items-center gap-2 rounded-lg border border-border bg-muted/30 px-3 py-2">
        <span className="w-8 shrink-0 text-xs font-semibold text-muted-foreground">#{index + 1}</span>
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-medium">
            {slot.kind === 'existing' ? slot.memberName : slot.memberName}
          </p>
          <p className="truncate text-xs text-muted-foreground">
            {slot.kind === 'existing'
              ? `@${slot.username ?? '—'} · existing`
              : 'New member · login password member123'}
          </p>
        </div>
        <Button type="button" size="sm" variant="ghost" onClick={onClear} aria-label="Clear seat">
          <X className="h-4 w-4" />
        </Button>
      </div>
    )
  }

  return (
    <div ref={wrapRef} className="relative flex items-start gap-2 rounded-lg border border-dashed border-border px-3 py-2">
      <span className="mt-2.5 w-8 shrink-0 text-xs font-semibold text-muted-foreground">#{index + 1}</span>
      <div className="relative min-w-0 flex-1">
        <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          className="pl-8"
          placeholder="Search member or type a new name…"
          value={query}
          onChange={(e) => {
            setQuery(e.target.value)
            setOpen(true)
          }}
          onFocus={() => setOpen(true)}
        />
        {open ? (
          <div className="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-md border border-border bg-card shadow-md">
            {matches.map((m) => (
              <button
                key={m.id}
                type="button"
                className="flex w-full items-start gap-2 px-3 py-2 text-left text-sm hover:bg-muted"
                onClick={() => {
                  onSelect({
                    kind: 'existing',
                    memberId: m.id,
                    memberName: m.memberName,
                    username: m.username,
                  })
                  setQuery('')
                  setOpen(false)
                }}
              >
                <span className="font-medium">{m.memberName}</span>
                <span className="text-muted-foreground">@{m.username ?? '—'}</span>
              </button>
            ))}
            {matches.length === 0 && !canCreateNew ? (
              <p className="px-3 py-2 text-sm text-muted-foreground">No matches. Type at least 2 letters to create new.</p>
            ) : null}
            {canCreateNew ? (
              <button
                type="button"
                className={cn(
                  'flex w-full items-center gap-2 border-t border-border px-3 py-2 text-left text-sm text-primary hover:bg-teal-soft',
                )}
                onClick={() => {
                  onSelect({ kind: 'new', memberName: query.trim() })
                  setQuery('')
                  setOpen(false)
                }}
              >
                <UserPlus className="h-4 w-4" />
                Create new “{query.trim()}”
              </button>
            ) : null}
            {!q ? (
              <p className="border-t border-border px-3 py-1.5 text-xs text-muted-foreground">
                Tip: pick from the list, or type a full name to create.
              </p>
            ) : null}
          </div>
        ) : null}
      </div>
      {canCreateNew && open ? (
        <Button
          type="button"
          size="sm"
          variant="secondary"
          className="mt-0.5 shrink-0"
          onClick={() => {
            onSelect({ kind: 'new', memberName: query.trim() })
            setQuery('')
            setOpen(false)
          }}
        >
          <Plus className="h-4 w-4" />
          New
        </Button>
      ) : null}
    </div>
  )
}
