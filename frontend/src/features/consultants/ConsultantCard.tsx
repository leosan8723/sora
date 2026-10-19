import { Link } from 'react-router'

import { useI18n } from '../../features/i18n/useI18n'
import type { ConsultantSummary } from './types'

function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) {
    return '؟'
  }
  if (parts.length === 1) {
    return parts[0].slice(0, 2)
  }
  return parts[0].charAt(0) + parts[1].charAt(0)
}

export function ConsultantCard({ consultant }: { consultant: ConsultantSummary }) {
  const { t, fmt, locale: loc } = useI18n()

  return (
    <Link
      to={`/consultants/${consultant.id}`}
      className="group flex flex-col gap-5 rounded-card border border-hairline bg-surface p-5 transition-colors hover:border-accent/40 hover:bg-surface-strong"
    >
      <div className="flex items-start gap-4">
        <span
          aria-hidden="true"
          className="grid size-12 shrink-0 place-items-center rounded-panel bg-tint text-lg font-semibold text-accent-strong"
        >
          {initials(consultant.name)}
        </span>
        <div className="flex min-w-0 flex-col gap-1">
          <div className="flex items-center gap-2">
            <h3 className="truncate text-base text-ink">{consultant.name}</h3>
            {consultant.is_verified ? <VerifiedMark label={t('consultants.verified')} /> : null}
          </div>
          {consultant.headline ? (
            <p className="line-clamp-2 text-sm text-ink-70">{consultant.headline}</p>
          ) : null}
        </div>
      </div>

      {consultant.specialties.length > 0 ? (
        <ul className="flex flex-wrap gap-2">
          {consultant.specialties.slice(0, 3).map((specialty) => (
            <li
              key={specialty.id}
              className="rounded-control bg-tint px-2.5 py-1 text-xs text-ink-70"
            >
              {specialty.name}
            </li>
          ))}
        </ul>
      ) : null}

      <div className="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-hairline pt-3 text-sm">
        <span className="text-ink-50">
          {consultant.years_experience
            ? t('consultants.experience', {
                years: fmt.number(consultant.years_experience),
              })
            : t('consultants.noExperience')}
        </span>
        {consultant.reviews_count && consultant.reviews_count > 0 ? (
          <span className="flex items-center gap-1 text-ink-70">
            <span aria-hidden="true" className="text-accent">
              ★
            </span>
            {fmt.number(consultant.avg_rating ?? 0)}
            <span className="text-ink-50">
              ({t('consultants.reviewsCount', { count: fmt.number(consultant.reviews_count) })})
            </span>
          </span>
        ) : null}
        <span className="text-accent">
          {loc === 'en' && consultant.starting_price_usd_cents != null
            ? t('consultants.from', { price: fmt.usd(consultant.starting_price_usd_cents) })
            : loc === 'en'
              ? t('consultants.usdNotSet')
              : consultant.starting_price_toman !== null
                ? t('consultants.from', {
                    price: fmt.toman(consultant.starting_price_toman),
                  })
                : t('consultants.noPrice')}
        </span>
      </div>
    </Link>
  )
}

function VerifiedMark({ label }: { label: string }) {
  return (
    <span
      title={label}
      className="inline-flex size-5 items-center justify-center rounded-full bg-accent/15 text-accent"
    >
      <svg viewBox="0 0 20 20" className="size-3.5" aria-hidden="true">
        <path
          d="M5 10.5l3.2 3.2L15 7"
          fill="none"
          stroke="currentColor"
          strokeWidth="2.2"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </svg>
      <span className="sr-only">{label}</span>
    </span>
  )
}
