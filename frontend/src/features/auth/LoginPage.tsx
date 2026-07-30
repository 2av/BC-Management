import { useState, type FormEvent } from 'react'
import { Link, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { ArrowLeft, Download, Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { apiFetch } from '@/shared/api/client'
import { useAuth, type AuthUser, type UserRole } from '@/features/auth/AuthContext'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { LanguageSwitcher } from '@/components/layout/LanguageSwitcher'
import { PasswordInput } from '@/components/ui/password-input'

function safeReturnPath(raw: string | null | undefined, home: string): string {
  if (!raw) return home
  if (!raw.startsWith('/') || raw.startsWith('//')) return home
  if (raw.startsWith('/login')) return home
  return raw
}

export function LoginPage() {
  const { t } = useTranslation()
  const { portal = 'member' } = useParams()
  const location = useLocation()
  const [searchParams] = useSearchParams()
  const portalMap: Record<string, { role: UserRole; title: string; home: string; blurb: string }> = {
    admin: {
      role: 'ClientAdmin',
      title: t('login.adminTitle'),
      home: '/admin',
      blurb: t('login.adminBlurb'),
    },
    member: {
      role: 'Member',
      title: t('login.memberTitle'),
      home: '/member',
      blurb: t('login.memberBlurb'),
    },
    'super-admin': {
      role: 'SuperAdmin',
      title: t('login.superAdminTitle'),
      home: '/super-admin',
      blurb: t('login.superAdminBlurb'),
    },
  }
  const config = portalMap[portal]
  const { login } = useAuth()
  const navigate = useNavigate()
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const sessionExpired = searchParams.get('expired') === '1'
  const isMember = portal === 'member'

  if (!config) {
    return (
      <div className="grid min-h-screen place-items-center bg-background p-6">
        <Card className="max-w-md">
          <CardHeader>
            <CardTitle>{t('login.unknownPortal')}</CardTitle>
            <CardDescription>{t('login.unknownPortalDesc')}</CardDescription>
          </CardHeader>
          <CardContent>
            <Button asChild>
              <Link to="/login/member">{t('login.backToMember')}</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setLoading(true)
    try {
      const user = await apiFetch<AuthUser>('/api/auth/login', {
        method: 'POST',
        body: JSON.stringify({
          username,
          password,
          portal: config.role,
        }),
      })
      login(user)
      const fromState = (location.state as { from?: { pathname?: string; search?: string } } | null)?.from
      const fromPath = fromState?.pathname
        ? `${fromState.pathname}${fromState.search ?? ''}`
        : null
      if (user.mustChangePassword && config.role === 'Member') {
        navigate('/member/account')
        return
      }
      const next = safeReturnPath(searchParams.get('returnUrl') ?? fromPath, config.home)
      navigate(next)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login failed')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="relative min-h-screen bg-background">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(15,118,110,0.12),transparent_40%),radial-gradient(circle_at_bottom_right,rgba(11,31,51,0.08),transparent_35%)]" />
      <div className="relative mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-12">
        <div className="mb-4 flex items-center justify-between">
          {isMember ? (
            <span className="text-sm text-muted-foreground">{t('app.brandFull')}</span>
          ) : (
            <Link
              to="/login/member"
              className="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
            >
              <ArrowLeft className="h-4 w-4" />
              {t('login.backToMember')}
            </Link>
          )}
          <LanguageSwitcher />
        </div>

        <Card className={`shadow-md ${isMember ? 'border-primary/30 ring-1 ring-primary/15' : ''}`}>
          <CardHeader>
            <p className="font-display text-lg text-primary">{t('app.brandFull')}</p>
            <CardTitle className="text-2xl">{config.title}</CardTitle>
            <CardDescription>{config.blurb}</CardDescription>
          </CardHeader>
          <CardContent>
            <form className="space-y-4" onSubmit={onSubmit}>
              {sessionExpired ? (
                <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                  Your session expired. Please sign in again.
                </div>
              ) : null}
              {error ? (
                <div className="whitespace-pre-wrap break-words rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-destructive">
                  {error}
                </div>
              ) : null}
              <div className="space-y-2">
                <Label htmlFor="username">{t('login.username')}</Label>
                <Input
                  id="username"
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  autoComplete="username"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="password">{t('login.password')}</Label>
                <PasswordInput
                  id="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  autoComplete="current-password"
                  required
                />
              </div>
              <Button type="submit" className="w-full" disabled={loading}>
                {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                {loading ? t('common.loading') : t('login.submit')}
              </Button>
            </form>
          </CardContent>
        </Card>

        {isMember ? (
          <>
            <a
              href="/app/MitraNiidhi.apk?v=1.0.1"
              download="MitraNiidhi.apk"
              target="_blank"
              rel="noopener noreferrer"
              className="mt-5 flex items-center gap-3 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 transition hover:border-primary/40 hover:bg-primary/10"
            >
              <img
                src="/app/mitra-niidhi-icon.png"
                alt=""
                width={48}
                height={48}
                className="h-12 w-12 shrink-0 rounded-xl shadow-sm"
              />
              <span className="min-w-0 flex-1 text-left">
                <span className="block text-sm font-semibold text-foreground">{t('login.downloadAppTitle')}</span>
                <span className="mt-0.5 block text-xs text-muted-foreground">{t('login.downloadAppBlurb')}</span>
              </span>
              <span className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground">
                <Download className="h-3.5 w-3.5" />
                {t('login.downloadApk')}
              </span>
            </a>

            <p className="mt-5 text-center text-xs text-muted-foreground">
              <span>{t('landing.staffAccess')} </span>
              <Link to="/login/admin" className="underline-offset-2 hover:text-foreground hover:underline">
                {t('landing.adminTitle')}
              </Link>
              <span aria-hidden="true"> · </span>
              <Link to="/login/super-admin" className="underline-offset-2 hover:text-foreground hover:underline">
                {t('landing.superAdminTitle')}
              </Link>
            </p>
          </>
        ) : null}
      </div>
    </div>
  )
}
