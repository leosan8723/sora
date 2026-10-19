import { useAdminSummary } from '../../features/admin/useAdmin'
import { useI18n } from '../../features/i18n/useI18n'
import { Skeleton } from '../../components/ui/Skeleton'
import { StateMessage } from '../../components/ui/StateMessage'

export function AdminSummaryPage() {
  const { t } = useI18n()
  const summary = useAdminSummary()

  if (summary.isPending) {
    return (
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-hidden="true">
        {Array.from({ length: 6 }).map((_, index) => (
          <Skeleton key={index} className="h-28 w-full rounded-card" />
        ))}
      </div>
    )
  }

  if (summary.isError || !summary.data) {
    return (
      <StateMessage
        tone="error"
        title={t('admin.summaryLoadFailed')}
        action={
          <button
            type="button"
            className="text-sm text-accent"
            onClick={() => summary.refetch()}
          >
            {t('common.retry')}
          </button>
        }
      />
    )
  }

  const data = summary.data

  return (
    <div className="flex flex-col gap-5">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <StatCard
          title={t('admin.nav.users')}
          rows={[
            [t('admin.summary.total'), data.users.total],
            [t('admin.summary.clients'), data.users.clients],
            [t('admin.summary.consultants'), data.users.consultants],
            [t('admin.summary.admins'), data.users.admins],
          ]}
        />
        <StatCard
          title={t('nav.consultants')}
          rows={[
            [t('admin.summary.profiles'), data.consultants.profiles],
            [t('admin.summary.verified'), data.consultants.verified],
            [t('admin.summary.discoverable'), data.consultants.discoverable],
          ]}
        />
        <StatCard
          title={t('admin.nav.messages')}
          rows={[
            [t('admin.summary.unhandledMessages'), data.contact_messages.unhandled],
          ]}
        />
      </div>

      <div className="rounded-card border border-hairline bg-surface p-6">
        <h2 className="text-lg">{t('admin.nav.appointments')}</h2>
        <dl className="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-6">
          <SummaryItem label={t('admin.summary.total')} value={data.appointments.total} />
          <SummaryItem label={t('appointments.status.pending')} value={data.appointments.pending} />
          <SummaryItem label={t('appointments.status.confirmed')} value={data.appointments.confirmed} />
          <SummaryItem label={t('appointments.status.completed')} value={data.appointments.completed} />
          <SummaryItem label={t('appointments.status.cancelled')} value={data.appointments.cancelled} />
          <SummaryItem label={t('admin.summary.upcoming7d')} value={data.appointments.upcoming_7d} />
        </dl>
      </div>
    </div>
  )
}

function StatCard({ title, rows }: { title: string; rows: [string, number][] }) {
  const { fmt } = useI18n()

  return (
    <div className="rounded-card border border-hairline bg-surface p-6">
      <h2 className="text-lg">{title}</h2>
      <dl className="mt-3 flex flex-col gap-2 text-sm">
        {rows.map(([label, value]) => (
          <div key={label} className="flex items-baseline justify-between gap-3">
            <dt className="text-ink-50">{label}</dt>
            <dd className="text-lg text-ink">{fmt.number(value)}</dd>
          </div>
        ))}
      </dl>
    </div>
  )
}

function SummaryItem({ label, value }: { label: string; value: number }) {
  const { fmt } = useI18n()

  return (
    <div className="flex flex-col gap-1">
      <dt className="text-xs text-ink-50">{label}</dt>
      <dd className="text-base text-ink">{fmt.number(value)}</dd>
    </div>
  )
}
