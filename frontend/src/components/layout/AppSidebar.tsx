import type { ReactNode } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import { X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'

export type NavItem = {
  to: string
  label: string
  icon: ReactNode
  /** When set, overrides default NavLink active matching */
  isActive?: (pathname: string) => boolean
  end?: boolean
}

export function AppSidebar({
  brand,
  subtitle,
  items,
  footer,
  mobileOpen,
  onNavigate,
  onClose,
}: {
  brand: string
  subtitle?: string
  items: NavItem[]
  footer?: ReactNode
  mobileOpen?: boolean
  onNavigate?: () => void
  onClose?: () => void
}) {
  const { pathname } = useLocation()

  const aside = (
    <aside className="flex h-full w-64 shrink-0 flex-col border-r border-white/10 bg-navy text-white">
      <div className="flex items-start justify-between border-b border-white/10 px-5 py-5">
        <div>
          <p className="font-display text-xl tracking-tight">{brand}</p>
          {subtitle ? <p className="mt-1 text-xs text-white/60">{subtitle}</p> : null}
        </div>
        {onClose ? (
          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="text-white hover:bg-white/10 lg:hidden"
            onClick={onClose}
            aria-label="Close menu"
          >
            <X className="h-5 w-5" />
          </Button>
        ) : null}
      </div>
      <nav className="min-h-0 flex-1 space-y-1 overflow-y-auto p-3">
        {items.map((item) => {
          const active = item.isActive
            ? item.isActive(pathname)
            : item.end
              ? pathname === item.to || pathname === `${item.to}/`
              : pathname === item.to || pathname.startsWith(`${item.to}/`)

          return (
            <NavLink
              key={item.label}
              to={item.to}
              end={item.end}
              onClick={onNavigate}
              aria-current={active ? 'page' : undefined}
              className={cn(
                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-white/75 transition-colors hover:bg-white/10 hover:text-white',
                active && 'bg-primary text-white shadow-sm',
              )}
            >
              <span className="opacity-90">{item.icon}</span>
              {item.label}
            </NavLink>
          )
        })}
      </nav>
      {footer ? <div className="border-t border-white/10 p-4">{footer}</div> : null}
    </aside>
  )

  return (
    <>
      {/* Desktop sticky sidebar */}
      <div className="sticky top-0 hidden h-screen shrink-0 lg:block">{aside}</div>

      {/* Mobile drawer */}
      <div
        className={cn(
          'fixed inset-0 z-50 lg:hidden',
          mobileOpen ? 'pointer-events-auto' : 'pointer-events-none',
        )}
      >
        <button
          type="button"
          aria-label="Close menu overlay"
          className={cn(
            'absolute inset-0 bg-black/50 transition-opacity',
            mobileOpen ? 'opacity-100' : 'opacity-0',
          )}
          onClick={onClose}
        />
        <div
          className={cn(
            'absolute inset-y-0 left-0 h-full shadow-xl transition-transform duration-200',
            mobileOpen ? 'translate-x-0' : '-translate-x-full',
          )}
        >
          {aside}
        </div>
      </div>
    </>
  )
}
