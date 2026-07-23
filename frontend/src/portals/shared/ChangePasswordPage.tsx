import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/api/client'
import { useAuth } from '@/features/auth/AuthContext'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

type Profile = {
  id: number
  username: string
  fullName: string
  email: string | null
  phone: string | null
  role: string
  clientId: number | null
}

export function ChangePasswordPage() {
  const { t } = useTranslation()
  const api = useApi()
  const { user, login } = useAuth()
  const qc = useQueryClient()
  const [fullName, setFullName] = useState('')
  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [currentPassword, setCurrent] = useState('')
  const [newPassword, setNew] = useState('')
  const [confirm, setConfirm] = useState('')
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const { data: profile } = useQuery({
    queryKey: ['auth-profile'],
    queryFn: () => api.get<Profile>('/api/auth/me/profile'),
    enabled: user?.role !== 'Member',
  })

  useEffect(() => {
    if (!profile) return
    setFullName(profile.fullName)
    setEmail(profile.email ?? '')
    setPhone(profile.phone ?? '')
  }, [profile])

  const saveProfile = useMutation({
    mutationFn: () => api.patch<Profile>('/api/auth/me/profile', { fullName, email, phone }),
    onSuccess: (data) => {
      setMessage('Profile updated.')
      setError(null)
      if (user) {
        login({ ...user, fullName: data.fullName })
      }
      void qc.invalidateQueries({ queryKey: ['auth-profile'] })
    },
    onError: (e: Error) => setError(e.message),
  })

  const changePassword = useMutation({
    mutationFn: () => api.post('/api/auth/change-password', { currentPassword, newPassword }),
    onSuccess: () => {
      setMessage('Password updated.')
      setError(null)
      setCurrent('')
      setNew('')
      setConfirm('')
    },
    onError: (e: Error) => setError(e.message),
  })

  function submitPassword() {
    if (newPassword !== confirm) {
      setError('New passwords do not match.')
      return
    }
    changePassword.mutate()
  }

  const isMember = user?.role === 'Member'

  return (
    <div>
      <PageHeader
        title={t('account.title')}
        description={isMember ? 'Update your login password.' : 'Update your profile and password.'}
        actions={<Badge>{user?.role}</Badge>}
      />
      {message ? <p className="mb-3 text-sm text-emerald-700">{message}</p> : null}
      {error ? <p className="mb-3 text-sm text-destructive">{error}</p> : null}

      <div className="grid gap-4 lg:grid-cols-2">
        {!isMember ? (
          <Card>
            <CardHeader>
              <CardTitle>{t('account.profile')}</CardTitle>
              <CardDescription>@{profile?.username ?? user?.username}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="space-y-1.5">
                <Label>{t('account.fullName')}</Label>
                <Input value={fullName} onChange={(e) => setFullName(e.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('account.email')}</Label>
                <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('account.phone')}</Label>
                <Input value={phone} onChange={(e) => setPhone(e.target.value)} />
              </div>
              <Button
                disabled={saveProfile.isPending || !fullName.trim()}
                onClick={() => {
                  setMessage(null)
                  saveProfile.mutate()
                }}
              >
                {t('account.saveProfile')}
              </Button>
            </CardContent>
          </Card>
        ) : (
          <Card>
            <CardHeader>
              <CardTitle>{user?.fullName}</CardTitle>
              <CardDescription>@{user?.username}</CardDescription>
            </CardHeader>
            <CardContent className="text-sm text-muted-foreground">
              Edit contact details from the Profile page.
            </CardContent>
          </Card>
        )}

        <Card>
          <CardHeader>
            <CardTitle>{t('account.changePassword')}</CardTitle>
            <CardDescription>Choose a strong password of at least 6 characters.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="space-y-1.5">
              <Label>{t('account.currentPassword')}</Label>
              <Input type="password" value={currentPassword} onChange={(e) => setCurrent(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>{t('account.newPassword')}</Label>
              <Input type="password" value={newPassword} onChange={(e) => setNew(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>{t('account.confirmPassword')}</Label>
              <Input type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} />
            </div>
            <Button
              disabled={changePassword.isPending || !currentPassword || !newPassword}
              onClick={() => {
                setMessage(null)
                submitPassword()
              }}
            >
              {t('account.changePassword')}
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
