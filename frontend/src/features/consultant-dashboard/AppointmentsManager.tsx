import { useCallback, useState } from 'react'
import { Link } from 'react-router'
import type { ReactNode } from 'react'

import { Button } from '../../components/ui/Button'
import { Skeleton } from '../../components/ui/Skeleton'
import { StateMessage } from '../../components/ui/StateMessage'
import { AppointmentCard } from '../booking/AppointmentCard'
import type { Appointment, AppointmentScope } from '../booking/types'
import { useAppointments, useUpdateAppointmentStatus } from '../booking/useBooking'
import { useI18n } from '../../features/i18n/useI18n'
import { ApiError, errorMessage } from '../../lib/api'

const LIST_SCOPES: AppointmentScope[] = ['upcoming', 'past']

export function AppointmentsManager() {
  const { t, fmt } = useI18n()
  const [scope, setScope] = useState<AppointmentScope>('upcoming')

  const appointments = useAppointments({ scope, perPage: 10 })

  const list = appointments.data?.data ?? []
  const meta = appointments.data?.meta

  return (
    <section className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-col gap-1">
          <h2 className="text-xl">{t('dashboard.sessionsTitle')}</h2>
          <p className="text-sm text-ink-70">{t('dashboard.sessionsText')}</p>
        </div>

        <div
          role="tablist"
          aria-label={t('appointments.scopeAria')}
          className="flex flex-wrap gap-2"
        >
          {LIST_SCOPES.map((value) => {
            const selected = value === scope
            return (
              <button
                key={value}
                type="button"
                role="tab"
                aria-selected={selected}
                onClick={() => setScope(value)}
                className={`rounded-control border px-3 py-2 text-sm transition-colors ${
                  selected
                    ? 'border-accent bg-accent-wash text-accent-strong'
                    : 'border-hairline bg-surface-strong text-ink-70 hover:text-ink'
                }`}
              >
                {t(`appointments.${value}`)}
              </button>
            )
          })}
        </div>
      </div>

      {appointments.isPending ? (
        <div className="flex flex-col gap-3" aria-hidden="true">
          <Skeleton className="h-36 w-full rounded-card" />
          <Skeleton className="h-36 w-full rounded-card" />
        </div>
      ) : appointments.isError ? (
        <StateMessage
          tone="error"
          title={t('appointments.loadFailed')}
          description={errorMessage(appointments.error, t('common.networkError'))}
          action={
            <Button
              variant="secondary"
              size="sm"
              onClick={() => appointments.refetch()}
            >
              {t('common.retry')}
            </Button>
          }
        />
      ) : list.length === 0 ? (
        <StateMessage
          title={
            scope === 'upcoming'
              ? t('dashboard.noUpcomingSessions')
              : t('appointments.noneInScope')
          }
          description={
            scope === 'upcoming' ? t('dashboard.noUpcomingSessionsText') : undefined
          }
        />
      ) : (
        <div className="flex flex-col gap-3">
          {list.map((appointment) => (
            <AppointmentCard
              key={appointment.id}
              appointment={appointment}
              linkTo={`/appointments/${appointment.id}`}
              showConsultant={false}
              showClient
              actions={<CardActions appointment={appointment} />}
            />
          ))}

          {meta && meta.last_page > 1 ? (
            <p className="text-sm text-ink-50">
              {t('dashboard.morePages', {
                current: fmt.number(meta.current_page),
                total: fmt.number(meta.last_page),
              })}
            </p>
          ) : null}
        </div>
      )}
    </section>
  )
}

function CardActions({ appointment }: { appointment: Appointment }) {
  const updateStatus = useUpdateAppointmentStatus()
  const { t } = useI18n()
  const [confirming, setConfirming] = useState<'confirm' | 'complete' | null>(
    null,
  )
  const [error, setError] = useState<string | null>(null)

  const submit = useCallback(
    async (intent: 'confirm' | 'complete') => {
      setError(null)

      const status = intent === 'confirm' ? 'confirmed' : 'completed'

      try {
        await updateStatus.mutateAsync({ id: appointment.id, status })
        setConfirming(null)
      } catch (caught) {
        if (caught instanceof ApiError && caught.status === 409) {
          setError(t('appointmentDetail.slotConflict'))
          return
        }
        setError(errorMessage(caught, t('dashboard.statusUpdateFailed')))
      }
    },
    [appointment.id, updateStatus, t],
  )

  function renderButtons(): ReactNode {
    if (confirming !== null) {
      return (
        <span className="flex flex-wrap items-center gap-2">
          <span className="text-sm text-ink-70">
            {confirming === 'confirm'
              ? t('dashboard.confirmSessionPrompt')
              : t('dashboard.completeSessionPrompt')}
          </span>
          <Button
            size="sm"
            disabled={updateStatus.isPending}
            onClick={() => void submit(confirming)}
          >
            {updateStatus.isPending ? t('booking.submitting') : t('common.yes')}
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setConfirming(null)
              setError(null)
            }}
          >
            {t('common.cancel')}
          </Button>
        </span>
      )
    }

    return (
      <>
        {appointment.can.confirm ? (
          <Button size="sm" onClick={() => setConfirming('confirm')}>
            {t('dashboard.confirmSession')}
          </Button>
        ) : null}
        {appointment.can.complete ? (
          <Button
            size="sm"
            variant="secondary"
            onClick={() => setConfirming('complete')}
          >
            {t('dashboard.completeSession')}
          </Button>
        ) : null}
        <Link
          to={`/appointments/${appointment.id}`}
          className="text-sm text-accent transition-colors hover:text-ink"
        >
          {t('dashboard.sessionActionsLink')}
        </Link>
      </>
    )
  }

  return (
    <div className="flex flex-col gap-2">
      <div className="flex flex-wrap items-center gap-3">{renderButtons()}</div>
      {error ? (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      ) : null}
    </div>
  )
}
