import { useI18n } from '../../features/i18n/useI18n'

interface StarRatingDisplayProps {
  value: number
  /** Number of stars to draw; defaults to five. */
  outOf?: number
  size?: 'sm' | 'md'
}

const SIZE_CLASSES = {
  sm: 'text-sm',
  md: 'text-base',
} as const

/** Read-only star rating with a localized accessible label. */
export function StarRatingDisplay({ value, outOf = 5, size = 'sm' }: StarRatingDisplayProps) {
  const { t, fmt } = useI18n()
  const rounded = Math.round(value)

  return (
    <span
      className={`inline-flex items-center gap-0.5 text-accent ${SIZE_CLASSES[size]}`}
      role="img"
      aria-label={t('reviews.ratingSummary', {
        rating: fmt.number(value),
        count: fmt.number(outOf),
      })}
    >
      {Array.from({ length: outOf }, (_, index) => (
        <span key={index} aria-hidden="true" className={index < rounded ? '' : 'text-ink-30'}>
          ★
        </span>
      ))}
    </span>
  )
}

interface StarRatingInputProps {
  value: number
  onChange: (value: number) => void
  name: string
  disabled?: boolean
}

/** Accessible radio-based star picker for the review form. */
export function StarRatingInput({ value, onChange, name, disabled }: StarRatingInputProps) {
  const { t, fmt } = useI18n()

  return (
    <fieldset className="flex flex-col gap-2" disabled={disabled}>
      <legend className="text-sm text-ink-70">{t('reviews.ratingLegend')}</legend>
      <div className="flex flex-row-reverse justify-end gap-1" dir="ltr">
        {[1, 2, 3, 4, 5].map((score) => (
          <label
            key={score}
            className={`cursor-pointer px-0.5 text-2xl leading-none transition-colors ${
              score <= value ? 'text-accent' : 'text-ink-30 hover:text-accent/60'
            }`}
          >
            <input
              type="radio"
              name={name}
              value={score}
              checked={value === score}
              onChange={() => onChange(score)}
              className="sr-only"
              aria-label={t('reviews.scoreN', { score: fmt.number(score) })}
            />
            <span aria-hidden="true">★</span>
          </label>
        ))}
      </div>
    </fieldset>
  )
}
