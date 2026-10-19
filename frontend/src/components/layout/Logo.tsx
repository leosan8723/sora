import { Link } from 'react-router'

import { useI18n } from '../../features/i18n/useI18n'

export function Logo({ compact = false }: { compact?: boolean }) {
  const { t } = useI18n()

  return (
    <Link to="/" className="flex items-center gap-2.5" aria-label={t('brand.name')}>
      <span
        aria-hidden="true"
        className="flex size-9 items-center justify-center rounded-control bg-tint"
      >
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
          {/* A stylised unfolding leaf / breath mark. */}
          <path
            d="M10 16.5C10 16.5 4.5 13.5 4.5 8.5C4.5 5.5 7 3.5 10 3.5C13 3.5 15.5 5.5 15.5 8.5C15.5 13.5 10 16.5 10 16.5Z"
            stroke="var(--color-accent)"
            strokeWidth="1.5"
            strokeLinecap="round"
          />
          <path
            d="M10 16V8"
            stroke="var(--color-accent)"
            strokeWidth="1.5"
            strokeLinecap="round"
          />
        </svg>
      </span>
      {!compact ? (
        <span className="font-display text-xl font-semibold text-ink">{t('brand.name')}</span>
      ) : null}
    </Link>
  )
}
