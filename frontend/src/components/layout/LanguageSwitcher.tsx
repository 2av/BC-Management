import { useTranslation } from 'react-i18next'
import { setLanguage } from '@/i18n'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

export function LanguageSwitcher({ compact = false }: { compact?: boolean }) {
  const { i18n, t } = useTranslation()
  const lang = i18n.language.startsWith('hi') ? 'hi' : 'en'

  return (
    <div className={cn('flex items-center gap-1', compact && 'shrink-0')}>
      {!compact ? (
        <span className="hidden text-xs text-muted-foreground sm:inline">{t('common.language')}:</span>
      ) : null}
      <Button
        type="button"
        size="sm"
        variant={lang === 'en' ? 'default' : 'outline'}
        className={compact ? 'h-8 px-2.5' : undefined}
        onClick={() => setLanguage('en')}
      >
        EN
      </Button>
      <Button
        type="button"
        size="sm"
        variant={lang === 'hi' ? 'default' : 'outline'}
        className={compact ? 'h-8 px-2.5' : undefined}
        onClick={() => setLanguage('hi')}
      >
        HI
      </Button>
    </div>
  )
}
