import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import en from './locales/en.json'
import fr from './locales/fr.json'
import ka from './locales/ka.json'

export const SUPPORTED_LOCALES = ['fr', 'en', 'ka'] as const
export type Locale = (typeof SUPPORTED_LOCALES)[number]

const STORAGE_KEY = 'penderie.locale'
const DEFAULT_LOCALE: Locale = 'fr'

export function normalizeLocale(value: string | null | undefined): Locale {
  const base = value?.toLowerCase().split('-')[0]
  return SUPPORTED_LOCALES.find((locale) => locale === base) ?? DEFAULT_LOCALE
}

function readStoredLocale(): Locale {
  if (typeof window === 'undefined') return DEFAULT_LOCALE

  try {
    return normalizeLocale(window.localStorage.getItem(STORAGE_KEY))
  } catch {
    return DEFAULT_LOCALE
  }
}

const initialLocale = readStoredLocale()

void i18n.use(initReactI18next).init({
  resources: {
    fr: { translation: fr },
    en: { translation: en },
    ka: { translation: ka },
  },
  lng: initialLocale,
  fallbackLng: DEFAULT_LOCALE,
  supportedLngs: [...SUPPORTED_LOCALES],
  initImmediate: false,
  interpolation: { escapeValue: false },
  returnNull: false,
})

function applyLocale(value: string) {
  const locale = normalizeLocale(value)

  if (typeof document !== 'undefined') {
    document.documentElement.lang = locale
  }

  if (typeof window !== 'undefined') {
    try {
      window.localStorage.setItem(STORAGE_KEY, locale)
    } catch {
      // Storage can be unavailable in private browsing contexts.
    }
  }
}

applyLocale(initialLocale)
i18n.on('languageChanged', applyLocale)

export function setLocale(locale: Locale) {
  void i18n.changeLanguage(locale)
}

export default i18n
