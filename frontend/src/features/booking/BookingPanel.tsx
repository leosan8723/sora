import { useState } from 'react'

import { Button } from '../../components/ui/Button'
import { Skeleton } from '../../components/ui/Skeleton'
import { StateMessage } from '../../components/ui/StateMessage'
import { useI18n } from '../../features/i18n/useI18n'
import type { ConsultationMode } from '../consultants/types'
import { errorMessage } from '../../lib/api'
import { useSlots } from './useBooking'

export interface PanelConsultationType {
  id: number
  title: string
  duration_minutes: number
  price_toman?: number
  /** Integer cents; the server blocks English bookings without a valid fee. */
  price_usd_cents?: number | null
  mode?: ConsultationMode
}

export interface BookingSelection {
  consultationTypeId: number
  startsAt: string
  notes?: string | null
}

interface BookingPanelProps {
  consultantId: number
  types: PanelConsultationType[]
  fixedTypeId?: number
  /** Consultant display name, shown in the pre-confirmation summary. */
  consultantName?: string
  title: string
  submitLabel: string
  pending?: boolean
  error?: string | null
  withNotes?: boolean
  onConfirm: (selection: BookingSelection) => void
  onCancel?: () => void
}

export function BookingPanel({
  consultantId,
  types,
  fixedTypeId,
  consultantName,
  title,
  submitLabel,
  pending = false,
  error,
  withNotes = false,
  onConfirm,
  onCancel,
}: BookingPanelProps) {
  const { t, fmt, locale } = useI18n()
  const [selectedTypeId, setSelectedTypeId] = useState<number | null>(null)
  const [selectedDate, setSelectedDate] = useState<string | null>(null)
  const [selection, setSelection] = useState<{
    typeId: number
    startsAt: string
  } | null>(null)
  const [notes, setNotes] = useState('')

  const activeTypeId = fixedTypeId ?? selectedTypeId ?? types[0]?.id ?? null
  const slots = useSlots(consultantId, activeTypeId)
  const days = slots.data?.data ?? []
  const activeDay =
    days.find((day) => day.date === selectedDate) ?? days[0] ?? null

  const activeType = types.find((type) => type.id === activeTypeId) ?? null
  const chosenStartsAt =
    selection !== null && selection.typeId === activeTypeId
      ? selection.startsAt
      : null

  function chooseType(typeId: number) {
    setSelectedTypeId(typeId)
    setSelectedDate(null)
    setSelection(null)
  }

  // The server blocks English-locale bookings for session types without a
  // configured USD fee; mirror that rule so users are stopped early.
  const usdPriceMissing =
    locale === 'en' &&
    activeType != null &&
    typeof activeType.price_usd_cents !== 'number'

  const canConfirm =
    activeTypeId !== null && chosenStartsAt !== null && !pending && !usdPriceMissing

  return (
    <section className="flex flex-col gap-5 rounded-card border border-hairline bg-surface p-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex flex-col gap-1">
          <h2 className="text-xl">{title}</h2>
          <p className="text-sm text-ink-70">{t('booking.pickHint')}</p>
        </div>
        {onCancel ? (
          <Button variant="ghost" size="sm" onClick={onCancel}>
            {t('common.close')}
          </Button>
        ) : null}
      </div>

      {types.length > 1 && fixedTypeId === undefined ? (
        <div className="flex flex-col gap-2">
          <span className="text-sm text-ink-70">{t('booking.sessionType')}</span>
          <ul className="flex flex-wrap gap-2">
            {types.map((type) => {
              const selected = type.id === activeTypeId
              return (
                <li key={type.id}>
                  <button
                    type="button"
                    aria-pressed={selected}
                    onClick={() => chooseType(type.id)}
                    className={`rounded-control border px-3 py-2 text-sm transition-colors ${
                      selected
                        ? 'border-accent bg-accent-wash text-accent-strong'
                        : 'border-hairline bg-surface-strong text-ink-70 hover:text-ink'
                    }`}
                  >
                    {type.title} — {fmt.duration(type.duration_minutes)}
                  </button>
                </li>
              )
            })}
          </ul>
        </div>
      ) : null}

      {slots.isPending ? (
        <div className="flex flex-col gap-3" aria-hidden="true">
          <Skeleton className="h-9 w-2/3 rounded-control" />
          <Skeleton className="h-20 w-full rounded-panel" />
        </div>
      ) : slots.isError ? (
        <StateMessage
          tone="error"
          title={t('booking.slotsFailed')}
          description={errorMessage(slots.error, t('common.networkError'))}
          action={
            <Button variant="secondary" size="sm" onClick={() => slots.refetch()}>
              {t('common.retry')}
            </Button>
          }
        />
      ) : activeDay === null ? (
        <StateMessage
          title={t('booking.noSlots')}
          description={t('booking.noSlotsText')}
        />
      ) : (
        <div className="flex flex-col gap-4">
          <ul className="flex gap-2 overflow-x-auto pb-1">
            {days.map((day) => {
              const selected = day.date === activeDay.date
              return (
                <li key={day.date} className="shrink-0">
                  <button
                    type="button"
                    aria-pressed={selected}
                    onClick={() => {
                      setSelectedDate(day.date)
                      setSelection(null)
                    }}
                    className={`rounded-control border px-3 py-2 text-sm transition-colors ${
                      selected
                        ? 'border-accent bg-accent-wash text-accent-strong'
                        : 'border-hairline bg-surface-strong text-ink-70 hover:text-ink'
                    }`}
                  >
                    {fmt.day(day.date)}
                  </button>
                </li>
              )
            })}
          </ul>

          <ul className="grid grid-cols-3 gap-2 sm:grid-cols-4">
            {activeDay.slots.map((slot) => {
              const selected = slot.starts_at === chosenStartsAt
              return (
                <li key={slot.starts_at}>
                  <button
                    type="button"
                    aria-pressed={selected}
                    onClick={() =>
                      setSelection({
                        typeId: activeTypeId ?? 0,
                        startsAt: slot.starts_at,
                      })
                    }
                    className={`w-full rounded-control border px-2 py-2 text-sm transition-colors ${
                      selected
                        ? 'border-accent bg-accent-wash text-accent-strong'
                        : 'border-hairline bg-canvas/40 text-ink-70 hover:text-ink'
                    }`}
                  >
                    {fmt.digits(slot.label)}
                  </button>
                </li>
              )
            })}
          </ul>
        </div>
      )}

      {withNotes ? (
        <div className="flex flex-col gap-1.5">
          <label htmlFor="booking-notes" className="text-sm text-ink-70">
            {t('booking.notesLabel')}
          </label>
          <textarea
            id="booking-notes"
            value={notes}
            onChange={(event) => setNotes(event.target.value)}
            maxLength={1000}
            className="min-h-24 w-full rounded-control border border-hairline bg-surface px-3 py-2 text-ink placeholder:text-ink-50"
            placeholder={t('booking.notesPlaceholder')}
          />
        </div>
      ) : null}

      {chosenStartsAt && activeType ? (
        <dl className="grid grid-cols-2 gap-x-4 gap-y-2 rounded-panel border border-hairline bg-surface-soft px-5 py-4 text-sm sm:grid-cols-3">
          {consultantName ? (
            <SummaryItem label={t('booking.consultant')} value={consultantName} />
          ) : null}
          <SummaryItem label={t('booking.sessionType')} value={activeType.title} />
          {activeType.mode ? (
            <SummaryItem
              label={t('booking.mode')}
              value={t(`modes.${activeType.mode}`)}
            />
          ) : null}
          <SummaryItem label={t('common.date')} value={fmt.date(chosenStartsAt)} />
          <SummaryItem label={t('common.time')} value={fmt.time(chosenStartsAt)} />
          {activeType.price_toman !== undefined ? (
            <SummaryItem
              label={t('common.amount')}
              value={
                locale === 'en' && typeof activeType.price_usd_cents === 'number'
                  ? fmt.usd(activeType.price_usd_cents)
                  : fmt.toman(activeType.price_toman)
              }
            />
          ) : null}
        </dl>
      ) : null}

      {usdPriceMissing ? (
        <p role="alert" className="text-sm text-danger">
          {t('booking.usdPriceMissing')}
        </p>
      ) : null}

      {error ? (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      ) : null}

      <div className="flex flex-wrap items-center gap-3">
        <Button
          disabled={!canConfirm}
          onClick={() => {
            if (activeTypeId === null || chosenStartsAt === null) {
              return
            }
            onConfirm({
              consultationTypeId: activeTypeId,
              startsAt: chosenStartsAt,
              notes: withNotes ? notes.trim() || null : null,
            })
          }}
        >
          {pending ? t('booking.submitting') : submitLabel}
        </Button>
        {activeType && activeType.price_toman !== undefined ? (
          <span className="text-sm text-ink-50">
            {t('booking.sessionFee', {
              price:
                locale === 'en' && typeof activeType.price_usd_cents === 'number'
                  ? fmt.usd(activeType.price_usd_cents)
                  : fmt.toman(activeType.price_toman),
              modeSuffix: activeType.mode ? ` — ${t(`modes.${activeType.mode}`)}` : '',
            })}
          </span>
        ) : null}
      </div>
    </section>
  )
}

function SummaryItem({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col gap-0.5">
      <dt className="text-xs text-ink-50">{label}</dt>
      <dd className="text-ink">{value}</dd>
    </div>
  )
}
