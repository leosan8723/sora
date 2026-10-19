import { useState } from 'react'

import { Badge } from '../../components/ui/Badge'
import { Button } from '../../components/ui/Button'
import { TextAreaField } from '../../components/ui/Field'
import { Pagination } from '../../components/ui/Pagination'
import { StateMessage } from '../../components/ui/StateMessage'
import { useAuth } from '../auth/useAuth'
import { useI18n } from '../i18n/useI18n'
import { errorMessage } from '../../lib/api'
import { ReviewForm } from './ReviewForm'
import { StarRatingDisplay } from './StarRating'
import { useDeleteReview, useReviewableAppointments, useReviews, useSubmitReviewReport } from './useReviews'
import type { Review, ReviewStatus } from './types'

const STATUS_TONES: Record<ReviewStatus, 'neutral' | 'accent' | 'muted'> = {
  pending: 'neutral',
  approved: 'accent',
  rejected: 'muted',
  hidden: 'muted',
}

interface ReviewsSectionProps {
  consultantId: number
}

/**
 * Public review surface: aggregate rating, paginated approved reviews, and —
 * for a signed-in client with a completed visit — the review editor. Nothing
 * is fabricated: without approved reviews the section says so plainly.
 */
export function ReviewsSection({ consultantId }: ReviewsSectionProps) {
  const { user } = useAuth()
  const { t, fmt } = useI18n()
  const [page, setPage] = useState(1)
  const [editorOpen, setEditorOpen] = useState(false)
  const [editingReview, setEditingReview] = useState<Review | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [reportingId, setReportingId] = useState<number | null>(null)
  const [reportedIds, setReportedIds] = useState<ReadonlySet<number>>(new Set())
  const [reportReason, setReportReason] = useState('')
  const [reportError, setReportError] = useState<string | null>(null)

  const canReview = user?.role === 'client'
  const reviews = useReviews(consultantId, page)
  const reviewable = useReviewableAppointments(consultantId, canReview)
  const deleteReview = useDeleteReview(consultantId)
  const report = useSubmitReviewReport(consultantId)

  const metrics = reviews.data?.meta
  const items = reviews.data?.data ?? []
  const ownPending = items.filter(
    (review) => review.is_own && review.status !== 'approved',
  )
  // Own unpublished reviews get their own block; they never mix into the
  // public listing.
  const publishedItems = items.filter((review) => !review.is_own || review.status === 'approved')
  const reviewableAppointments = reviewable.data ?? []

  function closeEditor() {
    setEditorOpen(false)
    setEditingReview(null)
  }

  function submitReport(reviewId: number) {
    const reason = reportReason.trim()
    if (reason.length < 5) {
      setReportError(t('reviews.errors.reportBodyMin'))

      return
    }

    setReportError(null)
    report.mutate(
      { reviewId, reason },
      {
        onSuccess: () => {
          setReportedIds((previous) => new Set(previous).add(reviewId))
          setReportingId(null)
          setReportReason('')
          setNotice(t('reviews.reportSent'))
        },
        onError: (error) =>
          setReportError(errorMessage(error, t('reviews.reportFailed'))),
      },
    )
  }

  return (
    <section id="reviews" className="flex scroll-mt-24 flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-xl">{t('reviews.sectionTitle')}</h2>
        <div className="flex flex-wrap items-center gap-3">
          {metrics && metrics.reviews_count > 0 ? (
            <>
              <StarRatingDisplay value={metrics.avg_rating ?? 0} size="md" />
              <span className="text-sm text-ink-70">
                {t('reviews.ratingSummary', {
                  rating: fmt.number(metrics.avg_rating ?? 0),
                  count: fmt.number(metrics.reviews_count),
                })}
              </span>
            </>
          ) : (
            <Badge tone="muted">{t('reviews.noReviews')}</Badge>
          )}
        </div>
      </div>

      <p className="text-sm text-ink-50">{t('reviews.moderationNote')}</p>

      {notice ? <StateMessage tone="success" title={notice} /> : null}

      {ownPending.length > 0 ? (
        <div className="flex flex-col gap-3 rounded-card border border-dashed border-hairline p-5">
          <h3 className="text-base text-ink">{t('reviews.yourReview')}</h3>
          {ownPending.map((review) => (
            <div key={review.id} className="flex flex-col gap-2">
              <p className="flex flex-wrap items-center gap-3 text-sm text-ink-50">
                <StarRatingDisplay value={review.rating} />
                {review.status ? (
                  <Badge tone={STATUS_TONES[review.status]}>
                    {t(`reviews.status.${review.status}`)}
                  </Badge>
                ) : null}
              </p>
              <p className="leading-7 text-ink-80">{review.body}</p>
              <div className="flex flex-wrap gap-2">
                <Button
                  size="sm"
                  variant="secondary"
                  onClick={() => {
                    setEditingReview(review)
                    setEditorOpen(true)
                  }}
                >
                  {t('common.edit')}
                </Button>
                <Button
                  size="sm"
                  variant="ghost"
                  disabled={deleteReview.isPending}
                  onClick={() => {
                    setNotice(null)
                    deleteReview.mutate(review.id, {
                      onSuccess: () => setNotice(t('reviews.deletedNotice')),
                      onError: (error) =>
                        setNotice(errorMessage(error, t('reviews.deleteFailed'))),
                    })
                  }}
                >
                  {t('common.delete')}
                </Button>
              </div>
            </div>
          ))}
        </div>
      ) : null}

      {editorOpen && user ? (
        <ReviewForm
          consultantId={consultantId}
          appointments={reviewableAppointments}
          editing={editingReview}
          onDone={() => {
            closeEditor()
            setNotice(
              editingReview
                ? t('reviews.updatedNotice')
                : t('reviews.submittedNotice'),
            )
          }}
          onCancel={closeEditor}
        />
      ) : canReview ? (
        <div className="flex flex-wrap items-center gap-3">
          <Button
            onClick={() => {
              setNotice(null)
              setEditingReview(null)
              setEditorOpen(true)
            }}
          >
            {t('reviews.writeTitle')}
          </Button>
          <span className="text-sm text-ink-50">
            {reviewableAppointments.length > 0
              ? t('reviews.canWriteNow')
              : t('reviews.needCompletedSession')}
          </span>
        </div>
      ) : null}

      {reviews.isPending ? (
        <p className="text-sm text-ink-50">{t('common.loading')}</p>
      ) : reviews.isError ? (
        <StateMessage
          tone="error"
          title={t('reviews.loadFailed')}
          description={errorMessage(reviews.error, t('common.networkError'))}
          action={
            <Button variant="secondary" size="sm" onClick={() => reviews.refetch()}>
              {t('common.retry')}
            </Button>
          }
        />
      ) : publishedItems.length === 0 ? (
        <StateMessage
          title={t('reviews.emptyTitle')}
          description={t('reviews.emptyText')}
        />
      ) : (
        <ul className="flex flex-col gap-4">
          {publishedItems.map((review) => {
            const canReport =
              user !== null && review.status === 'approved' && !review.is_own

            return (
              <li
                key={review.id}
                className="flex flex-col gap-2 rounded-card border border-hairline bg-surface p-5"
              >
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="text-ink-80">{review.author}</span>
                  <div className="flex flex-wrap items-center gap-3">
                    <StarRatingDisplay value={review.rating} />
                    {review.created_at ? (
                      <span className="text-sm text-ink-50">
                        {fmt.date(review.created_at)}
                      </span>
                    ) : null}
                  </div>
                </div>
                <p className="leading-7 text-ink-70">{review.body}</p>

                {canReport ? (
                  reportedIds.has(review.id) ? (
                    <p className="text-xs text-ink-30">{t('reviews.reportAgain')}</p>
                  ) : (
                    <div className="flex flex-col items-start gap-2">
                      <Button
                        size="sm"
                        variant="ghost"
                        aria-expanded={reportingId === review.id}
                        onClick={() => {
                          setNotice(null)
                          setReportError(null)
                          setReportReason('')
                          setReportingId(
                            reportingId === review.id ? null : review.id,
                          )
                        }}
                      >
                        {t('reviews.report')}
                      </Button>
                      {reportingId === review.id ? (
                        <form
                          className="flex w-full max-w-xl flex-col gap-3 rounded-control border border-hairline bg-canvas p-4"
                          onSubmit={(event) => {
                            event.preventDefault()
                            submitReport(review.id)
                          }}
                        >
                          <p className="text-xs text-ink-50">{t('reviews.reportText')}</p>
                          <TextAreaField
                            name={`report-reason-${review.id}`}
                            label={t('reviews.reasonLabel')}
                            placeholder={t('reviews.reasonPlaceholder')}
                            error={reportError ?? undefined}
                            value={reportReason}
                            onChange={(event) => setReportReason(event.target.value)}
                            maxLength={500}
                            required
                            rows={3}
                          />
                          <div className="flex gap-2">
                            <Button size="sm" type="submit" disabled={report.isPending}>
                              {t('reviews.submitReport')}
                            </Button>
                            <Button
                              size="sm"
                              type="button"
                              variant="ghost"
                              onClick={() => setReportingId(null)}
                            >
                              {t('common.cancel')}
                            </Button>
                          </div>
                        </form>
                      ) : null}
                    </div>
                  )
                ) : null}
              </li>
            )
          })}
        </ul>
      )}

      {metrics && metrics.last_page > 1 ? (
        <Pagination
          page={metrics.current_page}
          lastPage={metrics.last_page}
          total={metrics.total}
          onPageChange={setPage}
        />
      ) : null}
    </section>
  )
}
