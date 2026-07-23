import type { ReactNode } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import { cn } from '@/lib/utils'

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
}: {
  brand: string
  subtitle?: string
  items: NavItem[]
  footer?: ReactNode
}) {
  const { pathname } = useLocation()

  return (
    <aside className="sticky top-0 flex h-screen w-64 shrink-0 flex-col border-r border-white/10 bg-navy text-white">
      <div className="border-b border-white/10 px-5 py-5">
        <p className="font-display text-xl tracking-tight">{brand}</p>
        {subtitle ? <p className="mt-1 text-xs text-white/60">{subtitle}</p> : null}
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
}
