import type { LocaleFormat } from './i18nContext'
import type { Locale } from './types'

const TEHRAN_TIME_ZONE = 'Asia/Tehran'

const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹']

const WEEKDAYS_FA = [
  'یکشنبه',
  'دوشنبه',
  'سه‌شنبه',
  'چهارشنبه',
  'پنجشنبه',
  'جمعه',
  'شنبه',
]

const WEEKDAYS_EN = [
  'Sunday',
  'Monday',
  'Tuesday',
  'Wednesday',
  'Thursday',
  'Friday',
  'Saturday',
]

const cached = new Map<Locale, LocaleFormat>()

export function createFormat(locale: Locale): LocaleFormat {
  const hit = cached.get(locale)
  if (hit) {
    return hit
  }

  const tag = locale === 'fa' ? 'fa-IR' : 'en-GB'
  const calendar = locale === 'fa' ? '-u-ca-persian' : ''

  const longDate = new Intl.DateTimeFormat(`${tag}${calendar}`, {
    dateStyle: 'full',
    timeZone: TEHRAN_TIME_ZONE,
  })
  const mediumDate = new Intl.DateTimeFormat(`${tag}${calendar}`, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    timeZone: TEHRAN_TIME_ZONE,
  })
  const clock = new Intl.DateTimeFormat(tag, {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: TEHRAN_TIME_ZONE,
  })
  const numberFormat = new Intl.NumberFormat(locale === 'fa' ? 'fa-IR' : 'en-US')

  const digits = (value: string) =>
    locale === 'fa' ? value.replace(/[0-9]/g, (d) => PERSIAN_DIGITS[Number(d)]) : value

  const format: LocaleFormat = {
    locale,
    date: (value) => digits(longDate.format(new Date(value))),
    day: (value) => digits(mediumDate.format(new Date(value))),
    time: (value) => digits(clock.format(new Date(value))),
    timeRange: (start, end) =>
      `${digits(clock.format(new Date(start)))} – ${digits(clock.format(new Date(end)))}`,
    number: (value) => numberFormat.format(value),
    toman: (value) =>
      locale === 'fa'
        ? `${numberFormat.format(value)} تومان`
        : `${numberFormat.format(value)} Toman`,
    // USD values arrive as integer cents; integer or fractional dollars
    // display cleanly without inventing any conversion factor.
    usd: (cents) =>
      new Intl.NumberFormat(locale === 'fa' ? 'fa-IR' : 'en-US', {
        style: 'currency',
        currency: 'USD',
        currencyDisplay: locale === 'fa' ? 'name' : 'symbol',
        maximumFractionDigits: Number.isInteger(cents / 100) ? 0 : 2,
      }).format(cents / 100),
    duration: (minutes) =>
      locale === 'fa' ? `${numberFormat.format(minutes)} دقیقه` : `${numberFormat.format(minutes)} min`,
    weekday: (weekday) => (locale === 'fa' ? WEEKDAYS_FA[weekday] : WEEKDAYS_EN[weekday]) ?? '',
    digits,
  }

  cached.set(locale, format)
  return format
}

/** Standalone digit conversion for places without the fmt helper. */
export function toDisplayDigits(locale: Locale, value: string): string {
  if (locale !== 'fa') {
    return value
  }
  return value.replace(/[0-9]/g, (d) => PERSIAN_DIGITS[Number(d)])
}
