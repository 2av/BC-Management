import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { apiFetch } from '@/shared/api/client'
import { useAuth, type AuthUser, type UserRole } from '@/features/auth/AuthContext'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { LanguageSwitcher } from '@/components/layout/LanguageSwitcher'

export function LoginPage() {
  const { t } = useTranslation()
  const { portal = 'admin' } = useParams()
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
              <Link to="/">{t('login.backHome')}</Link>
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
      navigate(config.home)
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
          <Link to="/" className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft className="h-4 w-4" />
            {t('login.backHome')}
          </Link>
          <LanguageSwitcher />
        </div>

        <Card className="shadow-md">
          <CardHeader>
            <p className="font-display text-lg text-primary">{t('app.brandFull')}</p>
            <CardTitle className="text-2xl">{config.title}</CardTitle>
            <CardDescription>{config.blurb}</CardDescription>
          </CardHeader>
          <CardContent>
            <form className="space-y-4" onSubmit={onSubmit}>
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
                <Input
                  id="password"
                  type="password"
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
      </div>
    </div>
  )
}
