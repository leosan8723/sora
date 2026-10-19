import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'

import { Badge } from '../components/ui/Badge'
import { Button } from '../components/ui/Button'
import { Skeleton } from '../components/ui/Skeleton'
import { StateMessage } from '../components/ui/StateMessage'
import { useAuth } from '../features/auth/useAuth'
import { BookingPanel } from '../features/booking/BookingPanel'
import type { BookingSelection } from '../features/booking/BookingPanel'
import { useCreateAppointment } from '../features/booking/useBooking'
import { useConsultant } from '../features/consultants/useConsultants'
import { ReviewsSection } from '../features/reviews/ReviewsSection'
import { StarRatingDisplay } from '../features/reviews/StarRating'
import { useI18n } from '../features/i18n/useI18n'
import { ApiError, errorMessage } from '../lib/api'

function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) {
    return '؟'
  }
  return parts.length === 1
    ? parts[0].slice(0, 2)
    : parts[0].charAt(0) + parts[1].charAt(0)
}

export function ConsultantDetailPage() {
  const { id } = useParams<{ id: string }>()
  const consultant = useConsultant(id ?? '')
  const { user } = useAuth()
  const { t, fmt, locale: loc } = useI18n()
  const navigate = useNavigate()
  const createAppointment = useCreateAppointment()
  const [bookingOpen, setBookingOpen] = useState(false)
  const [bookingError, setBookingError] = useState<string | null>(null)

  function handleBook(selection: BookingSelection) {
    setBookingError(null)
    createAppointment.mutate(
      {
        consultation_type_id: selection.consultationTypeId,
        starts_at: selection.startsAt,
        notes: selection.notes ?? null,
      },
      {
        onSuccess: (appointment) => {
          navigate(`/appointments/${appointment.id}`)
        },
        onError: (error: unknown) => {
          if (error instanceof ApiError && error.status === 409) {
            setBookingError(t('booking.slotTaken'))
            return
          }
          setBookingError(errorMessage(error, t('booking.failed')))
        },
      },
    )
  }

  if (!id) {
    return <StateMessage tone="error" title={t('consultantDetail.invalidId')} />
  }

  if (consultant.isPending) {
    return <DetailSkeleton />
  }

  if (consultant.isError) {
    const notFound =
      consultant.error instanceof ApiError && consultant.error.status === 404

    if (notFound) {
      return (
        <StateMessage
          title={t('consultantDetail.notFound')}
          description={t('consultantDetail.notFoundText')}
          action={
            <Link to="/consultants" className="text-sm text-accent">
              {t('consultantDetail.backToList')}
            </Link>
          }
        />
      )
    }

    return (
      <StateMessage
        tone="error"
        title={t('consultantDetail.loadFailed')}
        description={errorMessage(consultant.error, t('common.networkError'))}
        action={
          <Button variant="secondary" size="sm" onClick={() => consultant.refetch()}>
            {t('common.retry')}
          </Button>
        }
      />
    )
  }

  const data = consultant.data

  return (
    <article className="flex flex-col gap-8">
      <Link to="/consultants" className="text-sm text-ink-50 transition-colors hover:text-ink">
        {t('consultantDetail.backToList')}
      </Link>

      <header className="flex flex-col gap-5 rounded-card border border-hairline bg-surface p-6 sm:p-8">
        <div className="flex flex-col gap-5 sm:flex-row sm:items-start">
          <span
            aria-hidden="true"
            className="grid size-16 shrink-0 place-items-center rounded-panel bg-tint text-2xl font-semibold text-accent-strong"
          >
            {initials(data.name)}
          </span>
          <div className="flex flex-1 flex-col gap-3">
            <div className="flex flex-wrap items-center gap-3">
              <h1 className="text-2xl">{data.name}</h1>
              {data.is_verified ? <Badge tone="accent">{t('consultants.verified')}</Badge> : null}
            </div>
            {data.reviews_count && data.reviews_count > 0 ? (
              <p className="flex flex-wrap items-center gap-2 text-sm text-ink-70">
                <StarRatingDisplay value={data.avg_rating ?? 0} />
                <span>
                  {t('consultantDetail.ratingOutOf5', {
                    rating: fmt.number(data.avg_rating ?? 0),
                    count: fmt.number(data.reviews_count),
                  })}
                </span>
              </p>
            ) : (
              <p className="text-sm text-ink-50">{t('consultantDetail.noReviewsYet')}</p>
            )}
            {data.headline ? <p className="text-ink-80">{data.headline}</p> : null}
            {data.years_experience ? (
              <p className="text-sm text-ink-50">
                {t('consultants.experience', { years: fmt.number(data.years_experience) })}
              </p>
            ) : null}
            {data.specialties.length > 0 ? (
              <ul className="flex flex-wrap gap-2">
                {data.specialties.map((specialty) => (
                  <li key={specialty.id}>
                    <Badge tone="neutral">{specialty.name}</Badge>
                  </li>
                ))}
              </ul>
            ) : null}
          </div>
        </div>

        {data.bio ? <p className="max-w-3xl leading-7 text-ink-70">{data.bio}</p> : null}
      </header>

      <section className="flex flex-col gap-4">
        <h2 className="text-xl">{t('consultantDetail.typesTitle')}</h2>
        {data.consultation_types.length === 0 ? (
          <StateMessage
            title={t('consultantDetail.noTypes')}
            description={t('consultantDetail.noTypesText')}
          />
        ) : (
          <ul className="grid gap-4 sm:grid-cols-2">
            {data.consultation_types.map((type) => (
              <li
                key={type.id}
                className="flex flex-col gap-3 rounded-card border border-hairline bg-surface p-5"
              >
                <div className="flex items-start justify-between gap-4">
                  <h3 className="text-base text-ink">{type.title}</h3>
                  <span className="shrink-0 text-accent">
                    {loc === 'en' && type.price_usd_cents != null
                      ? fmt.usd(type.price_usd_cents)
                      : fmt.toman(type.price_toman)}
                  </span>
                </div>
                {type.description ? (
                  <p className="text-sm text-ink-70">{type.description}</p>
                ) : null}
                <p className="mt-auto flex flex-wrap items-center gap-2 text-sm text-ink-50">
                  <Badge tone={type.mode === 'online' ? 'accent' : 'neutral'}>
                    {t(`modes.${type.mode}`)}
                  </Badge>
                  {t('consultantDetail.duration', {
                    duration: fmt.duration(type.duration_minutes),
                  })}
                </p>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="flex flex-col gap-4">
        <h2 className="text-xl">{t('consultantDetail.availabilityTitle')}</h2>
        {data.availability.length === 0 ? (
          <StateMessage
            title={t('consultantDetail.noAvailability')}
            description={t('consultantDetail.noAvailabilityText')}
          />
        ) : (
          <ul className="flex flex-col gap-2 rounded-card border border-hairline bg-surface p-5">
            {data.availability.map((day) => (
              <li
                key={day.weekday}
                className="flex flex-wrap items-center justify-between gap-2 border-b border-hairline py-2 last:border-b-0"
              >
                <span className="text-ink-80">{fmt.weekday(day.weekday)}</span>
                <span className="text-sm text-ink-50">
                  {day.ranges
                    .map((range) =>
                      t('consultantDetail.timeRange', {
                        start: fmt.digits(range.start_time),
                        end: fmt.digits(range.end_time),
                      }),
                    )
                    .join(t('common.listSeparator'))}
                </span>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="flex flex-col gap-5">
        <ReviewsSection consultantId={data.id} />
      </section>

      {!user ? (
        <div className="flex flex-wrap items-center gap-4 rounded-card border border-hairline bg-surface p-6">
          <Button
            onClick={() =>
              navigate('/login', { state: { from: `/consultants/${data.id}` } })
            }
          >
            {t('consultantDetail.loginToBook')}
          </Button>
          <span className="text-sm text-ink-50">{t('consultantDetail.loginToBookText')}</span>
        </div>
      ) : user.role !== 'client' ? (
        <p className="rounded-card border border-dashed border-hairline p-6 text-sm text-ink-50">
          {t('consultantDetail.clientsOnly')}
        </p>
      ) : data.consultation_types.length === 0 ? (
        <StateMessage
          title={t('consultantDetail.cannotBook')}
          description={t('consultantDetail.cannotBookText')}
        />
      ) : bookingOpen ? (
        <BookingPanel
          consultantId={data.id}
          types={data.consultation_types}
          consultantName={data.name}
          title={t('booking.panelTitle')}
          submitLabel={t('booking.confirm')}
          withNotes
          pending={createAppointment.isPending}
          error={bookingError}
          onConfirm={handleBook}
          onCancel={() => setBookingOpen(false)}
        />
      ) : (
        <div className="flex flex-wrap items-center gap-4 rounded-card border border-hairline bg-surface p-6">
          <Button
            onClick={() => {
              setBookingError(null)
              setBookingOpen(true)
            }}
          >
            {t('consultantDetail.bookNow')}
          </Button>
          <span className="text-sm text-ink-50">{t('consultantDetail.bookNowText')}</span>
        </div>
      )}
    </article>
  )
}

function DetailSkeleton() {
  return (
    <div className="flex flex-col gap-8" aria-hidden="true">
      <Skeleton className="h-5 w-40" />
      <div className="flex flex-col gap-5 rounded-card border border-hairline bg-surface p-6">
        <div className="flex items-center gap-5">
          <Skeleton className="size-16 rounded-panel" />
          <div className="flex flex-1 flex-col gap-3">
            <Skeleton className="h-6 w-1/3" />
            <Skeleton className="h-4 w-2/3" />
          </div>
        </div>
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-4/5" />
      </div>
      <Skeleton className="h-40 w-full rounded-card" />
      <Skeleton className="h-32 w-full rounded-card" />
    </div>
  )
}
