import { useState } from 'react'

import { Badge } from '../../components/ui/Badge'
import { Button } from '../../components/ui/Button'
import { Pagination } from '../../components/ui/Pagination'
import { Skeleton } from '../../components/ui/Skeleton'
import { StateMessage } from '../../components/ui/StateMessage'
import { StarRatingDisplay } from '../../features/reviews/StarRating'
import {
  useAdminReviewReports,
  useDismissAdminReport,
  useResolveAdminReport,
} from '../../features/reviews/useReviews'
import type { ReviewReportStatus } from '../../features/reviews/types'
import { useI18n } from '../../features/i18n/useI18n'
import { errorMessage } from '../../lib/api'

const STATUS_VALUES: Array<'' | ReviewReportStatus> = ['', 'open', 'resolved', 'dismissed']

const STATUS_TONES: Record<ReviewReportStatus, 'accent' | 'success' | 'muted'> = {
  open: 'accent',
  resolved: 'success',
  dismissed: 'muted',
}

/**
 * Triage queue for reported reviews. Decisions only mark the report; the
 * review itself is moderated on the reviews page.
 */
export function AdminReportsPage() {
  const { t, fmt } = useI18n()
  const [status, setStatus] = useState<'' | ReviewReportStatus>('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState<string | null>(null)

  const reports = useAdminReviewReports({ status: status || undefined, page })
  const resolve = useResolveAdminReport()
  const dismiss = useDismissAdminReport()

  const busy = resolve.isPending || dismiss.isPending

  function decide(id: number, action: 'resolve' | 'dismiss') {
    setError(null)
    const mutation = action === 'resolve' ? resolve : dismiss
    mutation.mutate(id, {
      onError: (cause) =>
        setError(
          errorMessage(
            cause,
            action === 'resolve'
              ? t('admin.reports.resolveFailed')
              : t('admin.reports.dismissFailed'),
          ),
        ),
    })
  }

  return (
    <div className="flex flex-col gap-5">
      <p className="text-sm text-ink-50">{t('admin.reports.note')}</p>

      <div className="flex w-fit flex-wrap gap-1 rounded-control border border-hairline p-1 text-sm">
        {STATUS_VALUES.map((value) => (
          <button
            key={value}
            type="button"
            aria-pressed={status === value}
            onClick={() => {
              setStatus(value)
              setPage(1)
            }}
            className={`rounded-control px-3 py-1.5 transition-colors ${
              status === value ? 'bg-tint text-ink' : 'text-ink-70 hover:text-ink'
            }`}
          >
            {value === '' ? t('admin.roles.all') : t(`admin.reports.status.${value}`)}
          </button>
        ))}
      </div>

      {error ? (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      ) : null}

      {reports.isPending ? (
        <div className="flex flex-col gap-3" aria-hidden="true">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-32 w-full rounded-card" />
          ))}
        </div>
      ) : reports.isError ? (
        <StateMessage
          tone="error"
          title={t('admin.reports.loadFailed')}
          description={errorMessage(reports.error, t('common.networkError'))}
          action={
            <Button variant="secondary" size="sm" onClick={() => reports.refetch()}>
              {t('common.retry')}
            </Button>
          }
        />
      ) : reports.data.data.length === 0 ? (
        <StateMessage
          title={t('admin.reports.emptyTitle')}
          description={t('admin.reports.emptyText')}
        />
      ) : (
        <ul className="flex flex-col gap-3">
          {reports.data.data.map((report) => (
            <li key={report.id} className="rounded-card border border-hairline bg-surface p-5">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap items-center gap-3">
                  <Badge tone={STATUS_TONES[report.status]}>
                    {t(`admin.reports.status.${report.status}`)}
                  </Badge>
                  {report.created_at ? (
                    <span className="text-xs text-ink-30">{fmt.date(report.created_at)}</span>
                  ) : null}
                </div>
                <span className="text-sm text-ink-50">
                  {report.reporter
                    ? t('admin.reports.byReporter', { name: report.reporter.name })
                    : t('admin.reports.reporterUnknown')}
                </span>
              </div>

              <p className="mt-3 leading-7 text-ink-80">{report.reason}</p>

              {report.review ? (
                <div className="mt-3 rounded-control border border-hairline bg-canvas p-4">
                  <div className="flex flex-wrap items-center gap-3">
                    <StarRatingDisplay value={report.review.rating} />
                    <span className="text-sm text-ink-50">
                      {report.review.consultant?.name ?? t('admin.consultantUnknown')}
                    </span>
                    {report.review.client ? <span className="text-sm text-ink-30">{report.review.client.name}</span> : null}
                  </div>
                  <p className="mt-2 leading-7 text-ink-70">{report.review.body}</p>
                </div>
              ) : null}

              {report.status === 'open' ? (
                <div className="mt-3 flex flex-wrap gap-2">
                  <Button size="sm" disabled={busy} onClick={() => decide(report.id, 'resolve')}>
                    {t('admin.reports.resolve')}
                  </Button>
                  <Button
                    size="sm"
                    variant="secondary"
                    disabled={busy}
                    onClick={() => decide(report.id, 'dismiss')}
                  >
                    {t('admin.reports.dismiss')}
                  </Button>
                </div>
              ) : null}
            </li>
          ))}
        </ul>
      )}

      {reports.data ? (
        <Pagination
          page={reports.data.meta.current_page}
          lastPage={reports.data.meta.last_page}
          total={reports.data.meta.total}
          onPageChange={setPage}
        />
      ) : null}
    </div>
  )
}
