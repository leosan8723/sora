import { Button } from './Button'
import { useI18n } from '../../features/i18n/useI18n'

interface PaginationProps {
  page: number
  lastPage: number
  total: number
  onPageChange: (page: number) => void
}

export function Pagination({ page, lastPage, total, onPageChange }: PaginationProps) {
  const { t, fmt } = useI18n()

  if (lastPage <= 1) {
    return null
  }

  return (
    <nav
      aria-label={t('common.pagination')}
      className="flex flex-wrap items-center justify-between gap-4"
    >
      <p className="text-sm text-ink-50">
        {t('common.page', { current: fmt.number(page), total: fmt.number(lastPage) })}
        — {t('common.itemsCount', { count: fmt.number(total) })}
      </p>
      <div className="flex items-center gap-2">
        <Button
          variant="secondary"
          size="sm"
          disabled={page <= 1}
          onClick={() => onPageChange(page - 1)}
        >
          {t('common.previous')}
        </Button>
        <Button
          variant="secondary"
          size="sm"
          disabled={page >= lastPage}
          onClick={() => onPageChange(page + 1)}
        >
          {t('common.next')}
        </Button>
      </div>
    </nav>
  )
}
