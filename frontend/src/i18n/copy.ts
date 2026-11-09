export interface AppCopy {
  childSignInNotice: string
  unexpectedError: string
  nav: {
    friends: string
  }
  home: {
    titleLines: string[]
    tagline: string
    next: string
    signIn: string
    stepLabel: string
  }
  onboard: {
    next: string
    signIn: string
    organise: {
      titleLine: string
      introLines: string[]
      tabs: string[]
      rooms: string[]
      stepLabel: string
    }
    lend: {
      titleLines: string[]
      itemName: string
      status: string
      since: string
      borrower: string
      chips: string[]
      bodyLines: string[]
      timeline: string[]
      stepLabel: string
    }
    look: {
      titleLines: string[]
      bodyLines: string[]
      temperature: string
      today: string
      like: string
      skip: string
      caption: string
      stepLabel: string
    }
  }
  register: {
    titleLines: string[]
    intro: string
    usernameLabel: string
    emailLabel: string
    birthDateLabel: string
    passwordLabel: string
    usernamePlaceholder: string
    emailPlaceholder: string
    birthDatePlaceholder: string
    passwordPlaceholder: string
    birthDateHint: string
    termsLead: string
    termsLink: string
    submit: string
    switchPrompt: string
    switchLink: string
  }
  login: {
    title: string
    subtitle: string
    emailLabel: string
    passwordLabel: string
    emailPlaceholder: string
    passwordPlaceholder: string
    rememberMe: string
    forgotPassword: string
    submit: string
    switchPrompt: string
    switchLink: string
  }
  verify: {
    setupLater: string
    titleLines: string[]
    sentLead: string
    sentInstruction: string
    tokenNotice: string
    noTokenLead: string
    expiredError: string
    genericError: string
    emailLabel: string
    confirmButton: string
    confirmingButton: string
    resendButton: string
    resendingButton: string
    resendSuccess: string
    resendFailure: string
    switchPrompt: string
    switchLink: string
    backToLogin: string
  }
  profiles: {
    pinLabel: string
    pinNote: string
  }
  errors: {
    notFound: string
    conflict: string
    forbidden: string
    invalid: string
    rateLimited: string
  }
  friends: {
    title: string
    tagline: string
    addTitle: string
    addDescription: string
    usernameLabel: string
    usernamePlaceholder: string
    send: string
    follow: string
    followed: string
    following: string
    followers: string
    noFollowers: string
    noFollowing: string
    unfollow: string
    sending: string
    sent: string
    incoming: string
    noIncoming: string
    outgoing: string
    noOutgoing: string
    mine: string
    noFriends: string
    accept: string
    decline: string
    cancel: string
    remove: string
    adultOnly: string
    loading: string
  }
  account: {
    usernameTitle: string
    usernameDescription: string
    usernameLabel: string
    usernamePlaceholder: string
    usernameSave: string
    usernameSaving: string
    usernameSaved: string
    usernameTaken: string
    usernameError: string
  }
  rooms: {
    addRoom: string
    chooseRoom: string
    customOption: string
    customNameLabel: string
    customNamePlaceholder: string
    presets: string[]
  }
}

export const SUPPORTED_LOCALES = ['fr', 'en', 'ka'] as const
export type Locale = (typeof SUPPORTED_LOCALES)[number]

export const fallbackLng = 'fr'

/**
 * Get the i18next instance from the global window object.
 * The app mounts it via <I18nextProvider i18n={i18n}> in main.tsx.
 */
function getI18n() {
  if (typeof window !== 'undefined') {
    const possible = (window as any).i18next || (window as any).i18n
    if (possible && typeof possible.t === 'function') return possible
  }
  return null
}

/**
 * Detect the current locale from storage, document, or i18n instance.
 */
function detectLocale(): Locale {
  const i18n = getI18n()
  if (i18n) {
    try {
      const detected = i18n.languages?.[0] ?? i18n.language
      if (detected && SUPPORTED_LOCALES.includes(detected as Locale)) {
        return detected as Locale
      }
    } catch {
      // fall through
    }
  }
  if (typeof document !== 'undefined') {
    const docLang = (document.documentElement.lang || '').toLowerCase().split('-')[0]
    if (SUPPORTED_LOCALES.includes(docLang as Locale)) {
      return docLang as Locale
    }
  }
  return fallbackLng
}

function readStoredLocale(): Locale {
  if (typeof window === 'undefined') return fallbackLng
  try {
    return window.localStorage.getItem('penderie.locale')
      ?.toLowerCase()
      .split('-')[0] ?? fallbackLng
  } catch {
    return fallbackLng
  }
}

/**
 * Read a translation from i18next, returning undefined if i18next isn't available.
 * When i18next is available, returns the translated string or undefined if the key isn't found.
 */
function tRaw(key: string, locale: Locale): string | undefined {
  const i18n = getI18n()
  if (!i18n) return undefined
  try {
    const result = i18n.t(key, { interpolation: { escapeValue: false }, lng: locale })
    return typeof result === 'string' ? result : undefined
  } catch {
    return undefined
  }
}

/**
 * Main translation function. Returns the translated string, or undefined if i18next isn't available.
 * Components should handle the undefined case with fallback logic.
 */
export function t(key: string, locale?: Locale): string | undefined {
  const loc = locale ?? detectLocale()
  return tRaw(key, loc)
}

/**
 * Get an array of translations from i18next.
 * Returns an empty array if i18next isn't available.
 */
export function tArray<T>(key: string, locale?: Locale): T[] {
  const loc = locale ?? detectLocale()
  const i18n = getI18n()
  if (!i18n) return [] as T[]
  try {
    const result = i18n.t(key, { interpolation: { escapeValue: false }, lng: loc })
    return Array.isArray(result) ? result.map((item: any) => item as T) : [] as T[]
  } catch {
    return [] as T[]
  }
}

/**
 * Safely get a translation with a fallback.
 * Returns the translation if available, otherwise the defaultValue, or the key itself.
 */
export const getCopy = (key: string, defaultValue?: string): string => {
  const translation = t(key)
  if (translation !== undefined && translation !== '') return translation
  return defaultValue ?? key
}