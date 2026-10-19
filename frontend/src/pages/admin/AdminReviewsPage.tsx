import { useState } from 'react'

import { Badge } from '../../components/ui/Badge'
import { Button } from '../../components/ui/Button'
import { Pagination } from '../../components/ui/Pagination'
import { Skeleton } from '../../components/ui/Skeleton'
import { StateMessage } from '../../components/ui/StateMessage'
import { StarRatingDisplay } from '../../features/reviews/StarRating'
import {
  useAdminReviews,
  useDeleteAdminReview,
  useUpdateAdminReview,
} from '../../features/reviews/useReviews'
import type { ReviewStatus } from '../../features/reviews/types'
import { useI18n } from '../../features/i18n/useI18n'
import { errorMessage } from '../../lib/api'

const STATUS_VALUES: Array<'' | ReviewStatus> = ['', 'pending', 'approved', 'rejected', 'hidden']

const STATUS_TONES: Record<ReviewStatus, 'accent' | 'success' | 'neutral' | 'muted'> = {
  pending: 'accent',
  approved: 'success',
  rejected: 'muted',
  hidden: 'neutral',
}

/** Moderation queue: pending reviews first, with publish/reject/hide actions. */
export function AdminReviewsPage() {
  const { t, fmt } = useI18n()
  const [status, setStatus] = useState<'' | ReviewStatus>('')
  const [page, setPage] = useState(1)
  const [error, setError] = useState<string | null>(null)

  const reviews = useAdminReviews({ status: status || undefined, page })
  const update = useUpdateAdminReview()
  const remove = useDeleteAdminReview()

  const busy = update.isPending || remove.isPending

  function changeStatus(id: number, next: ReviewStatus) {
    setError(null)
    update.mutate(
      { id, status: next },
      { onError: (cause) => setError(errorMessage(cause, t('admin.errors.reviewUpdateFailed'))) },
    )
  }

  function deleteReview(id: number) {
    setError(null)
    remove.mutate(id, {
      onError: (cause) => setError(errorMessage(cause, t('reviews.deleteFailed'))),
    })
  }

  return (
    <div className="flex flex-col gap-5">
      <p className="text-sm text-ink-50">{t('admin.reviewsNote')}</p>

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
            {value === '' ? t('admin.roles.all') : t(`reviews.status.${value}`)}
          </button>
        ))}
      </div>

      {error ? (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      ) : null}

      {reviews.isPending ? (
        <div className="flex flex-col gap-3" aria-hidden="true">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-28 w-full rounded-card" />
          ))}
        </div>
      ) : reviews.isError ? (
        <StateMessage
          tone="error"
          title={t('admin.errors.reviewsLoadFailed')}
          description={errorMessage(reviews.error, t('common.networkError'))}
          action={
            <Button variant="secondary" size="sm" onClick={() => reviews.refetch()}>
              {t('common.retry')}
            </Button>
          }
        />
      ) : reviews.data.data.length === 0 ? (
        <StateMessage
          title={t('admin.reviewsEmptyTitle')}
          description={t('admin.reviewsEmptyText')}
        />
      ) : (
        <ul className="flex flex-col gap-3">
          {reviews.data.data.map((review) => (
            <li key={review.id} className="rounded-card border border-hairline bg-surface p-5">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap items-center gap-3">
                  <StarRatingDisplay value={review.rating} />
                  <Badge tone={STATUS_TONES[review.status]}>
                    {t(`reviews.status.${review.status}`)}
                  </Badge>
                  {review.created_at ? (
                    <span className="text-xs text-ink-30">
                      {fmt.date(review.created_at)}
                    </span>
                  ) : null}
                </div>
                <span className="text-sm text-ink-50">
                  {review.consultant?.name ?? t('admin.consultantUnknown')}
                </span>
              </div>

              <p className="mt-3 leading-7 text-ink-80">{review.body}</p>

              <p className="mt-2 text-sm text-ink-50">
                {review.client
                  ? `${review.client.name} — ${review.client.email}`
                  : t('admin.clientUnknown')}
                {review.appointment?.starts_at
                  ? ` — ${t('admin.sessionOn', { date: fmt.date(review.appointment.starts_at) })}`
                  : ''}
              </p>

              <div className="mt-3 flex flex-wrap gap-2">
                {review.status !== 'approved' ? (
                  <Button
                    size="sm"
                    disabled={busy}
                    onClick={() => changeStatus(review.id, 'approved')}
                  >
                    {t('admin.publish')}
                  </Button>
                ) : null}
                {review.status !== 'rejected' ? (
                  <Button
                    size="sm"
                    variant="secondary"
                    disabled={busy}
                    onClick={() => changeStatus(review.id, 'rejected')}
                  >
                    {t('admin.reject')}
                  </Button>
                ) : null}
                {review.status !== 'hidden' ? (
                  <Button
                    size="sm"
                    variant="ghost"
                    disabled={busy}
                    onClick={() => changeStatus(review.id, 'hidden')}
                  >
                    {t('admin.hide')}
                  </Button>
                ) : null}
                <Button
                  size="sm"
                  variant="danger"
                  disabled={busy}
                  onClick={() => deleteReview(review.id)}
                >
                  {t('common.delete')}
                </Button>
              </div>
            </li>
          ))}
        </ul>
      )}

      {reviews.data ? (
        <Pagination
          page={reviews.data.meta.current_page}
          lastPage={reviews.data.meta.last_page}
          total={reviews.data.meta.total}
          onPageChange={setPage}
        />
      ) : null}
    </div>
  )
}
