import { useTranslation } from 'react-i18next'
import { normalizeLocale, setLocale, SUPPORTED_LOCALES, type Locale } from '../i18n/config.ts'

const LABELS: Record<Locale, string> = {
  fr: 'Français',
  en: 'English',
  ka: 'ქართული',
}

interface LanguageSwitcherProps {
  compact?: boolean
  className?: string
}

export default function LanguageSwitcher({
  compact = false,
  className = '',
}: LanguageSwitcherProps) {
  const { i18n, t } = useTranslation()
  const current = normalizeLocale(i18n.resolvedLanguage ?? i18n.language)
  const classes = [
    'language-switcher',
    compact ? 'language-switcher--compact' : '',
    className,
  ]
    .filter(Boolean)
    .join(' ')

  return (
    <label className={classes}>
      <span className={compact ? 'sr-only' : 'language-switcher__label'}>
        {t('language.label')}
      </span>
      <select
        className="language-switcher__select"
        value={current}
        onChange={(event) => setLocale(normalizeLocale(event.target.value))}
        aria-label={t('language.label')}
      >
        {SUPPORTED_LOCALES.map((locale) => (
          <option key={locale} value={locale}>
            {LABELS[locale]}
          </option>
        ))}
      </select>
    </label>
  )
}
