import type { Locale } from './types'

const STORAGE_KEY = 'consultation.locale'

let current: Locale | null = null

export function getStoredLocale(): Locale | null {
  if (typeof window === 'undefined') {
    return null
  }
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY)
    return stored === 'fa' || stored === 'en' ? stored : null
  } catch {
    return null
  }
}

export function setStoredLocale(locale: Locale): void {
  current = locale
  try {
    window.localStorage.setItem(STORAGE_KEY, locale)
  } catch {
    /* storage unavailable; the choice still applies for this session */
  }
}

/**
 * The active locale, readable synchronously from non-React modules such as
 * the API client (so `Accept-Language` matches what the user sees).
 */
export function currentLocale(): Locale {
  return current ?? 'fa'
}

export function initLocale(): Locale {
  if (current) {
    return current
  }
  const stored = getStoredLocale()
  if (stored) {
    current = stored
    return stored
  }
  const fromBrowser =
    typeof navigator !== 'undefined' && navigator.language?.toLowerCase().startsWith('en')
  current = fromBrowser ? 'en' : 'fa'
  return current
}

export function applyLocaleDocument(locale: Locale): void {
  document.documentElement.lang = locale
  document.documentElement.dir = locale === 'fa' ? 'rtl' : 'ltr'
}
