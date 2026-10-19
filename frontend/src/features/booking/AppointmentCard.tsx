import type { ReactNode } from 'react'
import { Link } from 'react-router'

import { Badge } from '../../components/ui/Badge'
import { useI18n } from '../../features/i18n/useI18n'
import { APPOINTMENT_STATUS_TONES } from './types'
import type { Appointment } from './types'

interface AppointmentCardProps {
  appointment: Appointment
  /** Optional detail-page link target for the card title. */
  linkTo?: string
  /** Show the consultant summary row (client-facing lists). */
  showConsultant?: boolean
  /** Show the client row (consultant-facing lists). */
  showClient?: boolean
  /** Extra action buttons rendered under the card content. */
  actions?: ReactNode
}

export function AppointmentCard({
  appointment,
  linkTo,
  showConsultant = true,
  showClient = false,
  actions,
}: AppointmentCardProps) {
  const { t, fmt } = useI18n()
  const title = appointment.consultation_type?.title ?? t('appointments.defaultTitle')

  return (
    <section className="flex flex-col gap-4 rounded-card border border-hairline bg-surface p-6">
      <header className="flex flex-wrap items-center justify-between gap-2">
        <h3 className="text-lg">
          {linkTo ? (
            <Link to={linkTo} className="transition-colors hover:text-accent">
              {title}
            </Link>
          ) : (
            title
          )}
        </h3>
        <Badge tone={APPOINTMENT_STATUS_TONES[appointment.status]}>
          {t(`appointments.status.${appointment.status}`)}
        </Badge>
      </header>

      <dl className="grid gap-2 text-sm sm:grid-cols-2">
        <Detail label={t('common.date')} value={fmt.date(appointment.starts_at)} />
        <Detail
          label={t('common.time')}
          value={t('consultantDetail.timeRange', {
            start: fmt.digits(appointment.starts_at_tehran.slice(-5)),
            end: fmt.digits(appointment.ends_at_tehran.slice(-5)),
          })}
        />
        <Detail label={t('common.duration')} value={fmt.duration(appointment.duration_minutes)} />
        <Detail
          label={t('common.amount')}
          value={
            typeof appointment.price_usd_cents === 'number'
              ? `${fmt.toman(appointment.price_toman)} / ${fmt.usd(appointment.price_usd_cents)}`
              : fmt.toman(appointment.price_toman)
          }
        />
        {appointment.mode ? (
          <Detail
            label={t('booking.mode')}
            value={t(`modes.${appointment.mode}`)}
          />
        ) : null}
      </dl>

      {showConsultant && appointment.consultant ? (
        <p className="text-sm text-ink-70">
          {t('appointments.consultantLabel')}{' '}
          {linkTo ? (
            <Link
              to={`/consultants/${appointment.consultant.id}`}
              className="text-accent"
            >
              {appointment.consultant.name ?? t('appointments.consultantFallback')}
            </Link>
          ) : (
            (appointment.consultant.name ?? t('appointments.consultantFallback'))
          )}
        </p>
      ) : null}

      {showClient && appointment.client ? (
        <p className="text-sm text-ink-70">
          {t('appointments.clientLabel')}:{' '}
          {appointment.client.name ?? t('appointments.clientFallback')}
        </p>
      ) : null}

      {actions ? (
        <div className="flex flex-wrap items-center gap-3">{actions}</div>
      ) : null}
    </section>
  )
}

function Detail({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center gap-1.5">
      <dt className="text-ink-50">{label}:</dt>
      <dd className="text-ink-80">{value}</dd>
    </div>
  )
}
