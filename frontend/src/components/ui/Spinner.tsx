import { useI18n } from '../../features/i18n/useI18n'

interface SpinnerProps {
  label?: string
}

export function Spinner({ label }: SpinnerProps) {
  const { t } = useI18n()

  return (
    <span
      role="status"
      aria-live="polite"
      className="inline-flex items-center gap-2 text-ink-70"
    >
      <span
        aria-hidden="true"
        className="size-4 animate-spin rounded-full border-2 border-current border-t-transparent"
      />
      {label ?? t('common.loading')}
    </span>
  )
}
