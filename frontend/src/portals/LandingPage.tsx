import { Link } from 'react-router-dom'
import { ArrowRight, Building2, Shield, Users } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { LanguageSwitcher } from '@/components/layout/LanguageSwitcher'

export function LandingPage() {
  const { t } = useTranslation()
  const portals = [
    {
      to: '/login/admin',
      title: t('landing.adminTitle'),
      description: t('landing.adminDesc'),
      icon: Building2,
    },
    {
      to: '/login/member',
      title: t('landing.memberTitle'),
      description: t('landing.memberDesc'),
      icon: Users,
    },
    {
      to: '/login/super-admin',
      title: t('landing.superAdminTitle'),
      description: t('landing.superAdminDesc'),
      icon: Shield,
    },
  ]

  return (
    <div className="relative min-h-screen overflow-hidden bg-navy">
      <div className="pointer-events-none absolute inset-0">
        <div className="absolute -left-24 top-0 h-96 w-96 rounded-full bg-primary/30 blur-3xl" />
        <div className="absolute right-0 top-20 h-80 w-80 rounded-full bg-accent/20 blur-3xl" />
        <div className="absolute bottom-0 left-1/3 h-72 w-72 rounded-full bg-teal-soft/10 blur-3xl" />
      </div>

      <div className="relative mx-auto flex min-h-screen max-w-6xl flex-col justify-center px-6 py-16">
        <div className="mb-6 flex justify-end">
          <LanguageSwitcher />
        </div>
        <div className="mb-12 max-w-2xl">
          <p className="font-display text-2xl text-teal-soft sm:text-3xl">{t('app.brandFull')}</p>
          <h1 className="mt-4 text-4xl font-semibold tracking-tight text-white sm:text-5xl">
            {t('app.landingHeadline')}
          </h1>
          <p className="mt-4 max-w-xl text-base leading-relaxed text-white/70">{t('app.landingBlurb')}</p>
        </div>

        <div className="grid gap-4 md:grid-cols-3">
          {portals.map((portal) => {
            const Icon = portal.icon
            return (
              <Card
                key={portal.to}
                className="border-white/10 bg-white/95 backdrop-blur transition-transform duration-200 hover:-translate-y-1 hover:shadow-lg"
              >
                <CardHeader>
                  <div className="mb-2 flex h-11 w-11 items-center justify-center rounded-lg bg-teal-soft text-primary">
                    <Icon className="h-5 w-5" />
                  </div>
                  <CardTitle>{portal.title}</CardTitle>
                  <CardDescription>{portal.description}</CardDescription>
                </CardHeader>
                <CardContent>
                  <Button asChild className="w-full">
                    <Link to={portal.to}>
                      {t('app.continue')}
                      <ArrowRight className="h-4 w-4" />
                    </Link>
                  </Button>
                </CardContent>
              </Card>
            )
          })}
        </div>
      </div>
    </div>
  )
}
