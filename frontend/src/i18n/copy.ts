import i18n from './config.ts'

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

export const fallbackLng: Locale = 'fr'

/**
 * Translate a key with an optional fallback.
 * Returns the translated string when the key exists, otherwise the fallback
 * (when given) or the key itself.
 */
export function t(key: string, defaultValue?: string): string {
  const result = i18n.t(key)
  if (typeof result === 'string' && result !== '' && result !== key) return result
  return defaultValue ?? key
}

/**
 * Translate a key whose value is an array (for example title lines or presets).
 * Returns an empty array when the key is missing.
 */
export function tArray<T = string>(key: string): T[] {
  const result: unknown = i18n.t(key, { returnObjects: true })
  return Array.isArray(result) ? (result as T[]) : []
}

/**
 * Translate a key whose value is an object (for example a grouped block).
 * Returns an empty object when the key is missing.
 */
export function tObject<T extends object>(key: string): Partial<T> {
  const result: unknown = i18n.t(key, { returnObjects: true })
  return result !== null && typeof result === 'object' && !Array.isArray(result)
    ? (result as Partial<T>)
    : {}
}

/**
 * Backwards-compatible alias for `t`.
 */
export const getCopy = t