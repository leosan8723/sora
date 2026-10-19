import { useState } from 'react'

import { Pagination } from '../../components/ui/Pagination'
import { Skeleton } from '../../components/ui/Skeleton'
import { StateMessage } from '../../components/ui/StateMessage'
import { AppointmentCard } from '../../features/booking/AppointmentCard'
import { useAdminAppointments } from '../../features/admin/useAdmin'
import type { Appointment } from '../../features/booking/types'
import { useCancelAppointment } from '../../features/booking/useBooking'
import { useI18n } from '../../features/i18n/useI18n'
import { ApiError, errorMessage } from '../../lib/api'

const STATUS_VALUES = ['', 'pending', 'confirmed', 'completed', 'cancelled'] as const
const MODE_VALUES = ['', 'online', 'in_person'] as const

export function AdminAppointmentsPage() {
  const { t } = useI18n()
  const [status, setStatus] = useState('')
  const [mode, setMode] = useState('')
  const [page, setPage] = useState(1)

  const appointments = useAdminAppointments({
    status: status || undefined,
    mode: mode === '' ? undefined : (mode as 'online' | 'in_person'),
    page,
  })

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap gap-2">
        <div className="flex gap-1 rounded-control border border-hairline p-1 text-sm">
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
              {value === '' ? t('admin.roles.all') : t(`appointments.status.${value}`)}
            </button>
          ))}
        </div>
        <div className="flex gap-1 rounded-control border border-hairline p-1 text-sm">
          {MODE_VALUES.map((value) => (
            <button
              key={value}
              type="button"
              aria-pressed={mode === value}
              onClick={() => {
                setMode(value)
                setPage(1)
              }}
              className={`rounded-control px-3 py-1.5 transition-colors ${
                mode === value ? 'bg-tint text-ink' : 'text-ink-70 hover:text-ink'
              }`}
            >
              {value === '' ? t('admin.roles.all') : t(`modes.${value}`)}
            </button>
          ))}
        </div>
      </div>

      {appointments.isPending ? (
        <div className="flex flex-col gap-3" aria-hidden="true">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-40 w-full rounded-card" />
          ))}
        </div>
      ) : appointments.isError ? (
        <StateMessage tone="error" title={t('admin.errors.appointmentsLoadFailed')} />
      ) : appointments.data.data.length === 0 ? (
        <StateMessage title={t('admin.noAppointments')} description={t('admin.noUsersText')} />
      ) : (
        <ul className="flex flex-col gap-4">
          {appointments.data.data.map((appointment) => (
            <li key={appointment.id}>
              <AppointmentRow appointment={appointment} />
            </li>
          ))}
        </ul>
      )}

      {appointments.data ? (
        <Pagination
          page={appointments.data.meta.current_page}
          lastPage={appointments.data.meta.last_page}
          total={appointments.data.meta.total}
          onPageChange={setPage}
        />
      ) : null}
    </div>
  )
}

function AppointmentRow({ appointment }: { appointment: Appointment }) {
  const { t } = useI18n()
  const cancel = useCancelAppointment(appointment.id)
  const [error, setError] = useState<string | null>(null)

  return (
    <div className="flex flex-col gap-2">
      <AppointmentCard
        appointment={appointment}
        showConsultant
        showClient
        actions={
          appointment.status === 'pending' || appointment.status === 'confirmed' ? (
            <button
              type="button"
              className="text-sm text-danger transition-colors hover:text-danger"
              disabled={cancel.isPending}
              onClick={() => {
                setError(null)
                cancel.mutate(
                  { cancellation_reason: t('admin.cancelledByAdminReason') },
                  {
                    onError: (cause) => {
                      if (cause instanceof ApiError && cause.status === 422) {
                        setError(t('appointmentDetail.slotConflict'))
                        return
                      }
                      setError(errorMessage(cause, t('admin.errors.cancelFailed')))
                    },
                  },
                )
              }}
            >
              {t('appointmentDetail.cancel')}
            </button>
          ) : null
        }
      />
      {error ? (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      ) : null}
    </div>
  )
}
