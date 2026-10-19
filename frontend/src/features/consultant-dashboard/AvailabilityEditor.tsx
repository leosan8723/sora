import { useState } from 'react'

import { Badge } from '../../components/ui/Badge'
import { Button } from '../../components/ui/Button'
import { SelectField, TextField } from '../../components/ui/Field'
import { Skeleton } from '../../components/ui/Skeleton'
import { StateMessage } from '../../components/ui/StateMessage'
import { useI18n } from '../../features/i18n/useI18n'
import { ApiError, errorMessage } from '../../lib/api'
import type {
  AvailabilityRulePayload,
  ManagedAvailabilityRule,
} from './types'
import {
  useCreateOwnerAvailability,
  useDeleteOwnerAvailability,
  useOwnerAvailability,
  useUpdateOwnerAvailability,
} from './useConsultantDashboard'

const WEEKDAY_ORDER = [6, 0, 1, 2, 3, 4, 5] as const

export function AvailabilityEditor() {
  const { t, fmt } = useI18n()
  const availability = useOwnerAvailability()
  const create = useCreateOwnerAvailability()
  const update = useUpdateOwnerAvailability()
  const remove = useDeleteOwnerAvailability()

  const [creating, setCreating] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [pendingDeleteId, setPendingDeleteId] = useState<number | null>(null)

  const rules = availability.data ?? []
  const editingRule = rules.find((rule) => rule.id === editingId) ?? null

  return (
    <section className="flex flex-col gap-4 rounded-card border border-hairline bg-surface p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-col gap-1">
          <h2 className="text-xl">{t('dashboard.workingHoursTitle')}</h2>
          <p className="text-sm text-ink-70">{t('dashboard.workingHoursText')}</p>
        </div>
        {!creating ? (
          <Button
            size="sm"
            onClick={() => {
              setEditingId(null)
              setCreating(true)
            }}
          >
            {t('dashboard.addSlot')}
          </Button>
        ) : null}
      </div>

      {creating ? (
        <AvailabilityRuleForm
          error={create.error}
          pending={create.isPending}
          submitLabel={t('dashboard.addSlot')}
          onSubmit={(payload) =>
            create.mutate(payload, {
              onSuccess: () => setCreating(false),
            })
          }
          onCancel={() => setCreating(false)}
        />
      ) : null}

      {editingRule ? (
        <AvailabilityRuleForm
          initial={editingRule}
          error={update.error}
          pending={update.isPending}
          submitLabel={t('dashboard.saveChanges')}
          onSubmit={(payload) =>
            update.mutate(
              { id: editingRule.id, payload },
              { onSuccess: () => setEditingId(null) },
            )
          }
          onCancel={() => setEditingId(null)}
        />
      ) : null}

      {availability.isPending ? (
        <div className="flex flex-col gap-2" aria-hidden="true">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
        </div>
      ) : availability.isError ? (
        <StateMessage
          tone="error"
          title={t('dashboard.availabilityFailed')}
          description={errorMessage(availability.error, t('common.networkError'))}
          action={
            <Button
              variant="secondary"
              size="sm"
              onClick={() => availability.refetch()}
            >
              {t('common.retry')}
            </Button>
          }
        />
      ) : rules.length === 0 && !creating ? (
        <p className="rounded-panel border border-dashed border-hairline p-4 text-sm text-ink-50">
          {t('dashboard.noAvailabilityYet')}
        </p>
      ) : (
        <ul className="flex flex-col gap-2">
          {rules.map((rule) => (
            <li
              key={rule.id}
              className="flex flex-wrap items-center justify-between gap-3 border-b border-hairline py-2 last:border-b-0"
            >
              <div className="flex flex-wrap items-center gap-3">
                <span className="text-ink-80">{fmt.weekday(rule.weekday)}</span>
                <span className="text-sm text-ink-50">
                  {t('consultantDetail.timeRange', {
                    start: fmt.digits(rule.start_time),
                    end: fmt.digits(rule.end_time),
                  })}
                </span>
                {rule.is_active ? (
                  <Badge tone="accent">{t('dashboard.active')}</Badge>
                ) : (
                  <Badge tone="muted">{t('dashboard.inactive')}</Badge>
                )}
              </div>

              {pendingDeleteId === rule.id ? (
                <div className="flex items-center gap-2">
                  <span className="text-sm text-ink-70">{t('dashboard.deletePrompt')}</span>
                  <Button
                    variant="danger"
                    size="sm"
                    disabled={remove.isPending}
                    onClick={() => {
                      remove.mutate(rule.id, {
                        onSettled: () => setPendingDeleteId(null),
                      })
                    }}
                  >
                    {t('dashboard.confirmDelete')}
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setPendingDeleteId(null)}
                  >
                    {t('common.cancel')}
                  </Button>
                </div>
              ) : (
                <div className="flex items-center gap-2">
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => {
                      setCreating(false)
                      setEditingId(rule.id)
                    }}
                  >
                    {t('common.edit')}
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setPendingDeleteId(rule.id)}
                  >
                    {t('common.delete')}
                  </Button>
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

interface AvailabilityRuleFormProps {
  initial?: ManagedAvailabilityRule
  pending: boolean
  error?: unknown
  submitLabel: string
  onSubmit: (payload: AvailabilityRulePayload) => void
  onCancel: () => void
}

function AvailabilityRuleForm({
  initial,
  pending,
  error,
  submitLabel,
  onSubmit,
  onCancel,
}: AvailabilityRuleFormProps) {
  const { t, fmt } = useI18n()
  const [weekday, setWeekday] = useState<number>(initial?.weekday ?? 6)
  const [startTime, setStartTime] = useState(initial?.start_time ?? '09:00')
  const [endTime, setEndTime] = useState(initial?.end_time ?? '17:00')
  const [isActive, setIsActive] = useState(initial?.is_active ?? true)

  const apiError = error instanceof ApiError ? error : null
  const formMessage =
    apiError && apiError.errors.length === 0 ? apiError.message : null

  function submit() {
    onSubmit({
      weekday,
      start_time: startTime,
      end_time: endTime,
      is_active: isActive,
    })
  }

  return (
    <div className="flex flex-col gap-4 rounded-panel border border-hairline bg-canvas/40 p-4">
      <div className="grid gap-4 sm:grid-cols-3">
        <SelectField
          label={t('dashboard.weekday')}
          value={weekday}
          error={apiError?.fieldMessage('weekday')}
          onChange={(event) => setWeekday(Number(event.target.value))}
        >
          {WEEKDAY_ORDER.map((value) => (
            <option key={value} value={value}>
              {fmt.weekday(value)}
            </option>
          ))}
        </SelectField>
        <TextField
          label={t('dashboard.startTime')}
          type="time"
          value={startTime}
          error={apiError?.fieldMessage('start_time')}
          onChange={(event) => setStartTime(event.target.value)}
        />
        <TextField
          label={t('dashboard.endTime')}
          type="time"
          value={endTime}
          error={apiError?.fieldMessage('end_time')}
          onChange={(event) => setEndTime(event.target.value)}
        />
      </div>

      <label className="flex items-center gap-2 text-sm text-ink-70">
        <input
          type="checkbox"
          checked={isActive}
          onChange={(event) => setIsActive(event.target.checked)}
          className="size-4 accent-accent"
        />
        {t('dashboard.slotActive')}
      </label>

      {formMessage ? (
        <p role="alert" className="text-sm text-danger">
          {formMessage}
        </p>
      ) : null}

      <div className="flex flex-wrap items-center gap-2">
        <Button disabled={pending} onClick={submit}>
          {pending ? t('common.saving') : submitLabel}
        </Button>
        <Button variant="ghost" onClick={onCancel}>
          {t('common.cancel')}
        </Button>
      </div>
    </div>
  )
}
