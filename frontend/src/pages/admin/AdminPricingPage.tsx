import { useState } from 'react'

import { Badge } from '../../components/ui/Badge'
import { Button } from '../../components/ui/Button'
import { Pagination } from '../../components/ui/Pagination'
import { Skeleton } from '../../components/ui/Skeleton'
import { StateMessage } from '../../components/ui/StateMessage'
import { useAdminTypes, useUpdateAdminType } from '../../features/admin/useAdmin'
import type { AdminConsultationType } from '../../features/admin/types'
import { useI18n } from '../../features/i18n/useI18n'
import { ApiError, errorMessage } from '../../lib/api'

export function AdminPricingPage() {
  const { t } = useI18n()
  const [page, setPage] = useState(1)
  const types = useAdminTypes({ page })
  const update = useUpdateAdminType()
  const [editingId, setEditingId] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)

  function save(
    target: AdminConsultationType,
    price: number,
    usdCents: number | null,
    mode: AdminConsultationType['mode'],
  ) {
    setError(null)
    update.mutate(
      { id: target.id, payload: { price_toman: price, price_usd_cents: usdCents, mode } },
      {
        onSuccess: () => setEditingId(null),
        onError: (cause) => {
          if (cause instanceof ApiError && cause.errors.length > 0) {
            setError(cause.errors[0]?.message ?? t('admin.saveFailed'))
            return
          }
          setError(errorMessage(cause, t('admin.saveFailed')))
        },
      },
    )
  }

  return (
    <div className="flex flex-col gap-5">
      <p className="text-sm text-ink-50">{t('admin.pricingNote')}</p>

      {error ? (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      ) : null}

      {types.isPending ? (
        <div className="flex flex-col gap-3" aria-hidden="true">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-16 w-full rounded-card" />
          ))}
        </div>
      ) : types.isError ? (
        <StateMessage tone="error" title={t('admin.pricingLoadFailed')} />
      ) : types.data.data.length === 0 ? (
        <StateMessage title={t('admin.noTypes')} />
      ) : (
        <ul className="flex flex-col gap-3">
          {types.data.data.map((type) => (
            <li key={type.id}>
              {editingId === type.id ? (
                <TypeEditor type={type} onCancel={() => setEditingId(null)} onSave={save} />
              ) : (
                <TypeRow type={type} onEdit={() => setEditingId(type.id)} />
              )}
            </li>
          ))}
        </ul>
      )}

      {types.data ? (
        <Pagination
          page={types.data.meta.current_page}
          lastPage={types.data.meta.last_page}
          total={types.data.meta.total}
          onPageChange={setPage}
        />
      ) : null}
    </div>
  )
}

function TypeRow({ type, onEdit }: { type: AdminConsultationType; onEdit: () => void }) {
  const { t, fmt } = useI18n()

  return (
    <div className="flex flex-col gap-2 rounded-card border border-hairline bg-surface p-5 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex min-w-0 flex-col gap-1">
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-ink">{type.title}</span>
          <Badge tone={type.mode === 'online' ? 'accent' : 'neutral'}>
            {t(`modes.${type.mode}`)}
          </Badge>
          {!type.is_active ? <Badge tone="muted">{t('dashboard.inactive')}</Badge> : null}
        </div>
        <p className="text-sm text-ink-50">
          {t('dashboard.sessionsTitle')}: {type.consultant?.name ?? '—'} —{' '}
          {t('common.duration')}: {fmt.duration(type.duration_minutes)}
        </p>
      </div>
      <div className="flex shrink-0 items-center gap-4">
        <span className="text-ink">{fmt.toman(type.price_toman)}</span>
        {type.price_usd_cents !== null ? (
          <span className="text-ink-50">{fmt.usd(type.price_usd_cents)}</span>
        ) : (
          <Badge tone="muted">{t('admin.usdMissing')}</Badge>
        )}
        <Button variant="secondary" size="sm" onClick={onEdit}>
          {t('common.edit')}
        </Button>
      </div>
    </div>
  )
}

function TypeEditor({
  type,
  onSave,
  onCancel,
}: {
  type: AdminConsultationType
  onSave: (
    target: AdminConsultationType,
    price: number,
    usdCents: number | null,
    mode: AdminConsultationType['mode'],
  ) => void
  onCancel: () => void
}) {
  const { t, fmt } = useI18n()
  const [price, setPrice] = useState(String(type.price_toman))
  const [usd, setUsd] = useState(
    type.price_usd_cents === null ? '' : String(type.price_usd_cents / 100),
  )
  const [mode, setMode] = useState(type.mode)
  const parsedPrice = Number(price)
  const valid = Number.isInteger(parsedPrice) && parsedPrice >= 0

  // USD is entered in dollars and stored as integer cents; empty clears it.
  const parsedUsd = Number(usd)
  const usdCents =
    usd === '' ? null : Math.round(parsedUsd * 100) >= 0 && Number.isFinite(parsedUsd)
      ? Math.round(parsedUsd * 100)
      : NaN

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault()
        if (valid && usdCents !== null && !Number.isNaN(usdCents)) {
          onSave(type, parsedPrice, usdCents, mode)
        }
      }}
      className="flex flex-col gap-3 rounded-card border border-accent/40 bg-surface p-5"
    >
      <div className="grid items-end gap-3 md:grid-cols-[1fr_1fr_auto]">
        <label className="flex flex-col gap-1.5 text-sm">
          <span className="text-ink-70">{t('dashboard.priceLabel')}</span>
          <input
            type="number"
            min={0}
            step={1000}
            value={price}
            onChange={(event) => setPrice(event.target.value)}
            className="h-11 w-full rounded-panel border border-hairline bg-surface-soft px-3.5 text-ink focus:border-accent/50 focus:bg-surface focus:outline-none"
            aria-label={t('admin.priceAriaLabel')}
          />
          {Number.isFinite(parsedPrice) && parsedPrice > 0 ? (
            <span className="text-xs text-ink-50">{fmt.toman(parsedPrice)}</span>
          ) : null}
        </label>
        <label className="flex flex-col gap-1.5 text-sm">
          <span className="text-ink-70">{t('admin.usdLabel')}</span>
          <input
            type="number"
            min={0}
            step={0.01}
            dir="ltr"
            value={usd}
            placeholder={t('admin.usdEmpty')}
            onChange={(event) => setUsd(event.target.value)}
            className="h-11 w-full rounded-panel border border-hairline bg-surface-soft px-3.5 text-ink focus:border-accent/50 focus:bg-surface focus:outline-none"
            aria-label={t('admin.usdLabel')}
          />
          {usdCents !== null && !Number.isNaN(usdCents) && usdCents > 0 ? (
            <span className="text-xs text-ink-50">{fmt.usd(usdCents)}</span>
          ) : (
            <span className="text-xs text-ink-50">{t('admin.usdEmpty')}</span>
          )}
        </label>
        <label className="flex flex-col gap-1.5 text-sm">
          <span className="text-ink-70">{t('appointmentDetail.modeLabel')}</span>
          <select
            value={mode}
            onChange={(event) => setMode(event.target.value as AdminConsultationType['mode'])}
            className="h-11 rounded-panel border border-hairline bg-surface-soft px-3.5 text-ink focus:outline-none"
          >
            <option value="online">{t('modes.online')}</option>
            <option value="in_person">{t('modes.in_person')}</option>
          </select>
        </label>
        <div className="flex gap-2">
          <Button type="submit" size="md" disabled={!valid || Number.isNaN(usdCents ?? 0)}>
            {t('common.save')}
          </Button>
          <Button variant="ghost" size="md" onClick={onCancel}>
            {t('common.cancel')}
          </Button>
        </div>
      </div>
    </form>
  )
}
