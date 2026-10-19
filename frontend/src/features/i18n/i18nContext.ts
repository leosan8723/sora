import { createContext } from 'react'

import type { Locale } from './types'

/** Locale-aware formatting helpers shared through context. */
export interface LocaleFormat {
  /** Full date (fa: Jalali, en: Gregorian) in Tehran local time. */
  date: (value: string | Date) => string
  /** Clock time HH:mm in Tehran local time. */
  time: (value: string | Date) => string
  /** "HH:mm – HH:mm" range. */
  timeRange: (start: string | Date, end: string | Date) => string
  /** Weekday + day + month, e.g. "سه‌شنبه ۱۲ مهر". */
  day: (value: string | Date) => string
  number: (value: number) => string
  toman: (value: number) => string
  usd: (cents: number) => string
  duration: (minutes: number) => string
  weekday: (weekday: number) => string
  /** Convert ASCII digits to the locale's preferred digits. */
  digits: (value: string) => string
  /** Intl API error messages can be localized too. */
  locale: Locale
}

export interface I18nContextValue {
  locale: Locale
  setLocale: (locale: Locale) => void
  /** Translate a dot-path; dictionary values may be functions of vars. */
  t: (path: string, vars?: Record<string, string | number>) => string
  fmt: LocaleFormat
}

export const I18nContext = createContext<I18nContextValue | null>(null)
