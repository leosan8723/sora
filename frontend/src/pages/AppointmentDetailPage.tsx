import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'

import { Badge } from '../components/ui/Badge'
import { Button } from '../components/ui/Button'
import { Skeleton } from '../components/ui/Skeleton'
import { StateMessage } from '../components/ui/StateMessage'
import { BookingPanel } from '../features/booking/BookingPanel'
import type { PanelConsultationType } from '../features/booking/BookingPanel'
import { useAuth } from '../features/auth/useAuth'
import {
  useAppointment,
  useCancelAppointment,
  useRescheduleAppointment,
} from '../features/booking/useBooking'
import { APPOINTMENT_STATUS_TONES, isTerminalStatus } from '../features/booking/types'
import type { Appointment } from '../features/booking/types'
import { useI18n } from '../features/i18n/useI18n'
import { ApiError, errorMessage } from '../lib/api'

export function AppointmentDetailPage() {
  const { id } = useParams<{ id: string }>()
  const appointment = useAppointment(id ?? '')
  const { user } = useAuth()
  const { t, fmt } = useI18n()
  const navigate = useNavigate()
  const cancel = useCancelAppointment(id ?? '')
  const reschedule = useRescheduleAppointment(id ?? '')

  const [cancelOpen, setCancelOpen] = useState(false)
  const [cancelReason, setCancelReason] = useState('')
  const [rescheduleOpen, setRescheduleOpen] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)

  if (!user) {
    return <StateMessage title={t('appointmentDetail.loginRequired')} />
  }

  if (!id) {
    return <StateMessage tone="error" title={t('appointmentDetail.invalidId')} />
  }

  if (appointment.isPending) {
    return (
      <div className="flex flex-col gap-6" aria-hidden="true">
        <Skeleton className="h-5 w-40" />
        <Skeleton className="h-48 w-full rounded-card" />
      </div>
    )
  }

  if (appointment.isError) {
    const notFound =
      appointment.error instanceof ApiError && appointment.error.status === 404

    if (notFound) {
      return (
        <StateMessage
          title={t('appointmentDetail.notFound')}
          description={t('appointmentDetail.notFoundText')}
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
        title={t('appointmentDetail.loadFailed')}
        description={errorMessage(appointment.error, t('common.networkError'))}
        action={
          <Button
            variant="secondary"
            size="sm"
            onClick={() => appointment.refetch()}
          >
            {t('common.retry')}
          </Button>
        }
      />
    )
  }

  const data: Appointment = appointment.data
  const terminal = isTerminalStatus(data.status)
  const rescheduleTypes: PanelConsultationType[] = data.consultation_type
    ? [
        {
          id: data.consultation_type.id,
          title: data.consultation_type.title,
          duration_minutes: data.consultation_type.duration_minutes,
          mode: data.mode ?? undefined,
        },
      ]
    : []

  function handleCancel() {
    setActionError(null)
    cancel.mutate(
      { cancellation_reason: cancelReason.trim() || null },
      {
        onSuccess: () => {
          setCancelOpen(false)
          setCancelReason('')
        },
        onError: (error: unknown) => {
          setActionError(errorMessage(error, t('appointmentDetail.cancelFailed')))
        },
      },
    )
  }

  return (
    <article className="flex flex-col gap-6">
      <Link to="/consultants" className="text-sm text-ink-50 transition-colors hover:text-ink">
        {t('consultantDetail.backToList')}
      </Link>

      <header className="flex flex-col gap-4 rounded-card border border-hairline bg-surface p-6 sm:p-8">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h1 className="text-2xl">
            {data.consultation_type?.title ?? t('appointments.defaultTitle')}
          </h1>
          <Badge tone={APPOINTMENT_STATUS_TONES[data.status]}>
            {t(`appointments.status.${data.status}`)}
          </Badge>
        </div>

        {data.consultant ? (
          <p className="text-ink-80">
            {t('appointments.consultantLabel')}{' '}
            <Link
              to={`/consultants/${data.consultant.id}`}
              className="text-accent"
            >
              {data.consultant.name ?? t('appointments.consultantFallback')}
            </Link>
          </p>
        ) : null}

        <dl className="grid gap-3 sm:grid-cols-2">
          <Detail label={t('common.date')} value={fmt.date(data.starts_at)} />
          <Detail
            label={t('common.time')}
            value={t('consultantDetail.timeRange', {
              start: fmt.digits(data.starts_at_tehran.slice(-5)),
              end: fmt.digits(data.ends_at_tehran.slice(-5)),
            })}
          />
          <Detail label={t('common.duration')} value={fmt.duration(data.duration_minutes)} />
          <Detail
            label={t('common.amount')}
            value={
              typeof data.price_usd_cents === 'number'
                ? `${fmt.toman(data.price_toman)} / ${fmt.usd(data.price_usd_cents)}`
                : fmt.toman(data.price_toman)
            }
          />
          {data.mode ? (
            <Detail
              label={t('appointmentDetail.modeLabel')}
              value={t(`modes.${data.mode}`)}
            />
          ) : null}
        </dl>

        {data.notes ? (
          <div className="flex flex-col gap-1">
            <span className="text-sm text-ink-50">{t('appointmentDetail.yourNote')}</span>
            <p className="text-ink-80">{data.notes}</p>
          </div>
        ) : null}

        {data.status === 'cancelled' ? (
          <div className="flex flex-col gap-1 rounded-panel border border-hairline bg-canvas/40 p-4">
            <span className="text-sm text-ink-50">{t('appointmentDetail.cancelledNote')}</span>
            {data.cancellation_reason ? (
              <p className="text-ink-70">
                {t('appointmentDetail.cancelledReason', { reason: data.cancellation_reason })}
              </p>
            ) : null}
          </div>
        ) : null}
      </header>

      {!terminal && (data.can.cancel || data.can.reschedule) ? (
        <section className="flex flex-col gap-4">
          {rescheduleOpen && data.consultant && rescheduleTypes.length > 0 ? (
            <BookingPanel
              consultantId={data.consultant.id}
              types={rescheduleTypes}
              fixedTypeId={data.consultation_type?.id}
              title={t('appointmentDetail.rescheduleTitle')}
              submitLabel={t('appointmentDetail.rescheduleSubmit')}
              pending={reschedule.isPending}
              error={actionError}
              onConfirm={(selection) => {
                setActionError(null)
                reschedule.mutate(selection.startsAt, {
                  onSuccess: () => setRescheduleOpen(false),
                  onError: (error: unknown) => {
                    if (error instanceof ApiError && error.status === 409) {
                      setActionError(t('appointmentDetail.slotConflict'))
                      return
                    }
                    setActionError(
                      errorMessage(error, t('appointmentDetail.rescheduleFailed')),
                    )
                  },
                })
              }}
              onCancel={() => {
                setActionError(null)
                setRescheduleOpen(false)
              }}
            />
          ) : null}

          {cancelOpen ? (
            <div className="flex flex-col gap-4 rounded-card border border-hairline bg-surface p-6">
              <div className="flex flex-col gap-1">
                <h2 className="text-xl">{t('appointmentDetail.cancelTitle')}</h2>
                <p className="text-sm text-ink-70">
                  {t('appointmentDetail.cancelWarning')}
                </p>
              </div>
              <div className="flex flex-col gap-1.5">
                <label htmlFor="cancel-reason" className="text-sm text-ink-70">
                  {t('appointmentDetail.cancelReasonLabel')}
                </label>
                <textarea
                  id="cancel-reason"
                  value={cancelReason}
                  onChange={(event) => setCancelReason(event.target.value)}
                  maxLength={500}
                  className="min-h-20 w-full rounded-control border border-hairline bg-surface px-3 py-2 text-ink placeholder:text-ink-50"
                  placeholder={t('appointmentDetail.cancelReasonPlaceholder')}
                />
              </div>
              {actionError ? (
                <p role="alert" className="text-sm text-danger">
                  {actionError}
                </p>
              ) : null}
              <div className="flex flex-wrap items-center gap-3">
                <Button
                  variant="danger"
                  disabled={cancel.isPending}
                  onClick={handleCancel}
                >
                  {cancel.isPending
                    ? t('appointmentDetail.cancelling')
                    : t('appointmentDetail.cancelConfirm')}
                </Button>
                <Button
                  variant="ghost"
                  onClick={() => {
                    setCancelOpen(false)
                    setActionError(null)
                  }}
                >
                  {t('common.cancel')}
                </Button>
              </div>
            </div>
          ) : null}

          {!cancelOpen && !rescheduleOpen ? (
            <div className="flex flex-wrap items-center gap-3 rounded-card border border-hairline bg-surface p-6">
              {data.can.reschedule ? (
                <Button
                  onClick={() => {
                    setActionError(null)
                    setRescheduleOpen(true)
                  }}
                >
                  {t('appointmentDetail.reschedule')}
                </Button>
              ) : null}
              {data.can.cancel ? (
                <Button
                  variant="secondary"
                  onClick={() => {
                    setActionError(null)
                    setCancelOpen(true)
                  }}
                >
                  {t('appointmentDetail.cancel')}
                </Button>
              ) : null}
              {data.status === 'completed' && data.consultant ? (
                <Link
                  to={`/consultants/${data.consultant.id}#reviews`}
                  className="text-sm text-accent transition-colors hover:text-ink"
                >
                  {t('appointmentDetail.leaveReview')}
                </Link>
              ) : null}
            </div>
          ) : null}
        </section>
      ) : null}

      {terminal ? (
        <p className="rounded-card border border-dashed border-hairline p-6 text-sm text-ink-50">
          {t('appointmentDetail.terminalNote')}
        </p>
      ) : null}

      <div className="flex justify-end">
        <Button variant="ghost" onClick={() => navigate('/consultants')}>
          {t('common.back')}
        </Button>
      </div>
    </article>
  )
}

function Detail({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col gap-1">
      <dt className="text-sm text-ink-50">{label}</dt>
      <dd className="text-ink-80">{value}</dd>
    </div>
  )
}
