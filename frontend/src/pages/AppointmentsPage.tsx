import { useCallback } from 'react'
import { Link, useSearchParams } from 'react-router'

import { Button } from '../components/ui/Button'
import { Pagination } from '../components/ui/Pagination'
import { Skeleton } from '../components/ui/Skeleton'
import { StateMessage } from '../components/ui/StateMessage'
import { AppointmentCard } from '../features/booking/AppointmentCard'
import { APPOINTMENT_SCOPES } from '../features/booking/types'
import type { AppointmentScope } from '../features/booking/types'
import { useAppointments } from '../features/booking/useBooking'
import { useI18n } from '../features/i18n/useI18n'
import { errorMessage } from '../lib/api'

function readScope(value: string | null): AppointmentScope {
  return APPOINTMENT_SCOPES.includes(value as AppointmentScope)
    ? (value as AppointmentScope)
    : 'upcoming'
}

export function AppointmentsPage() {
  const { t } = useI18n()
  const [searchParams, setSearchParams] = useSearchParams()

  const scope = readScope(searchParams.get('scope'))
  const page = Math.max(1, Number(searchParams.get('page') ?? '1') || 1)

  const updateParams = useCallback(
    (patch: Record<string, string | undefined>) => {
      setSearchParams(
        (previous) => {
          const next = new URLSearchParams(previous)
          for (const [key, value] of Object.entries(patch)) {
            if (value === undefined || value === '') {
              next.delete(key)
            } else {
              next.set(key, value)
            }
          }
          return next
        },
        { replace: true },
      )
    },
    [setSearchParams],
  )

  const appointments = useAppointments({ scope, page })

  const list = appointments.data?.data ?? []
  const meta = appointments.data?.meta

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-col gap-1">
        <h1 className="text-3xl">{t('appointments.title')}</h1>
        <p className="text-ink-70">{t('appointments.subtitle')}</p>
      </header>

      <div
        role="tablist"
        aria-label={t('appointments.scopeAria')}
        className="flex flex-wrap gap-2"
      >
        {APPOINTMENT_SCOPES.map((value) => {
          const selected = value === scope
          return (
            <button
              key={value}
              type="button"
              role="tab"
              aria-selected={selected}
              onClick={() => updateParams({ scope: value, page: undefined })}
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

      {appointments.isPending ? (
        <div className="flex flex-col gap-4" aria-hidden="true">
          <Skeleton className="h-40 w-full rounded-card" />
          <Skeleton className="h-40 w-full rounded-card" />
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
              ? t('appointments.noUpcoming')
              : t('appointments.noneInScope')
          }
          description={
            scope === 'upcoming' ? t('appointments.noUpcomingText') : undefined
          }
          action={
            scope === 'upcoming' ? (
              <Link to="/consultants" className="text-sm text-accent">
                {t('nav.consultants')}
              </Link>
            ) : undefined
          }
        />
      ) : (
        <>
          <div className="flex flex-col gap-4">
            {list.map((appointment) => (
              <AppointmentCard
                key={appointment.id}
                appointment={appointment}
                linkTo={`/appointments/${appointment.id}`}
              />
            ))}
          </div>

          {meta ? (
            <Pagination
              page={meta.current_page}
              lastPage={meta.last_page}
              total={meta.total}
              onPageChange={(next) => updateParams({ page: String(next) })}
            />
          ) : null}
        </>
      )}
    </div>
  )
}
