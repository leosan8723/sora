import { useState } from 'react'

import { Button } from '../../components/ui/Button'
import { Skeleton } from '../../components/ui/Skeleton'
import { StateMessage } from '../../components/ui/StateMessage'
import { useAdminSettings, useUpdateAdminSettings } from '../../features/admin/useAdmin'
import type { AdminConsultationMode, AdminSettings } from '../../features/admin/types'
import { useI18n } from '../../features/i18n/useI18n'
import { ApiError, errorMessage } from '../../lib/api'

export function AdminSettingsPage() {
  const { t } = useI18n()
  const settings = useAdminSettings()

  if (settings.isPending) {
    return (
      <div className="flex flex-col gap-3" aria-hidden="true">
        <Skeleton className="h-24 w-full rounded-card" />
        <Skeleton className="h-32 w-full rounded-card" />
      </div>
    )
  }

  if (settings.isError || !settings.data) {
    return (
      <StateMessage
        tone="error"
        title={t('admin.errors.settingsLoadFailed')}
        action={
          <Button variant="secondary" size="sm" onClick={() => settings.refetch()}>
            {t('common.retry')}
          </Button>
        }
      />
    )
  }

  return <SettingsForm key={settings.dataUpdatedAt} initial={settings.data} />
}

type SettingsValues = {
  supported_modes: AdminConsultationMode[]
  contact_lines: string[]
}

function SettingsForm({ initial }: { initial: AdminSettings }) {
  const { t } = useI18n()
  const update = useUpdateAdminSettings()
  const [values, setValues] = useState<SettingsValues>({
    supported_modes: initial.supported_modes,
    contact_lines: initial.contact_lines,
  })
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  function setModes(
    transform: (modes: AdminConsultationMode[]) => AdminConsultationMode[],
  ) {
    setValues((previous) => ({
      ...previous,
      supported_modes: transform(previous.supported_modes),
    }))
  }

  function setLines(transform: (lines: string[]) => string[]) {
    setValues((previous) => ({
      ...previous,
      contact_lines: transform(previous.contact_lines),
    }))
  }

  return (
    <form
      className="flex flex-col gap-6"
      onSubmit={(event) => {
        event.preventDefault()
        setError(null)
        setMessage(null)
        update.mutate(
          {
            supported_modes: values.supported_modes,
            contact_lines: values.contact_lines.filter((line) => line.trim() !== ''),
          },
          {
            onSuccess: () => setMessage(t('admin.settingsSaved')),
            onError: (cause) => {
              if (cause instanceof ApiError && cause.errors.length > 0) {
                setError(cause.errors[0]?.message ?? t('admin.errors.settingsUpdateFailed'))
                return
              }
              setError(errorMessage(cause, t('admin.errors.settingsUpdateFailed')))
            },
          },
        )
      }}
    >
      <fieldset className="flex flex-col gap-3 rounded-card border border-hairline bg-surface p-6">
        <legend className="px-2 text-sm text-ink-70">{t('admin.settingsModesLegend')}</legend>
        <p className="text-sm text-ink-50">{t('admin.settingsModesText')}</p>
        <ModeCheckbox
          value="online"
          checked={values.supported_modes.includes('online')}
          onToggle={(on) =>
            setModes((modes) =>
              on ? [...modes, 'online'] : modes.filter((mode) => mode !== 'online'),
            )
          }
        />
        <ModeCheckbox
          value="in_person"
          checked={values.supported_modes.includes('in_person')}
          onToggle={(on) =>
            setModes((modes) =>
              on ? [...modes, 'in_person'] : modes.filter((mode) => mode !== 'in_person'),
            )
          }
        />
      </fieldset>

      <fieldset className="flex flex-col gap-3 rounded-card border border-hairline bg-surface p-6">
        <legend className="px-2 text-sm text-ink-70">{t('admin.settingsContactLegend')}</legend>
        <p className="text-sm text-ink-50">{t('admin.settingsContactText')}</p>
        {values.contact_lines.length === 0 ? (
          <p className="text-xs text-ink-30">{t('admin.settingsContactEmpty')}</p>
        ) : null}
        {values.contact_lines.map((line, index) => (
          <div key={index} className="flex items-center gap-2">
            <input
              value={line}
              aria-label={t('admin.settingsLineAria', { n: index + 1 })}
              onChange={(event) =>
                setLines((lines) =>
                  lines.map((item, itemIndex) =>
                    itemIndex === index ? event.target.value : item,
                  ),
                )
              }
              className="h-11 w-full rounded-panel border border-hairline bg-surface-soft px-3.5 text-ink focus:border-accent/50 focus:bg-surface focus:outline-none"
            />
            <Button
              variant="ghost"
              size="sm"
              type="button"
              onClick={() =>
                setLines((lines) => lines.filter((_, itemIndex) => itemIndex !== index))
              }
            >
              {t('common.delete')}
            </Button>
          </div>
        ))}
        <div>
          <Button
            variant="secondary"
            size="sm"
            type="button"
            onClick={() => setLines((lines) => [...lines, ''])}
          >
            {t('admin.settingsAddLine')}
          </Button>
        </div>
      </fieldset>

      {error ? (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      ) : null}
      {message ? <p className="text-sm text-success">{message}</p> : null}

      <div>
        <Button
          type="submit"
          disabled={update.isPending || values.supported_modes.length === 0}
        >
          {update.isPending ? t('profile.savingChanges') : t('common.save')}
        </Button>
      </div>
    </form>
  )
}

function ModeCheckbox({
  value,
  checked,
  onToggle,
}: {
  value: AdminConsultationMode
  checked: boolean
  onToggle: (on: boolean) => void
}) {
  const { t } = useI18n()

  return (
    <label className="flex items-start gap-3 text-sm">
      <input
        type="checkbox"
        className="mt-1 size-4 accent-[var(--color-accent)]"
        checked={checked}
        onChange={(event) => onToggle(event.target.checked)}
      />
      <span>
        <span className="text-ink">{t(`modes.${value}`)}</span>
        <span className="block text-xs text-ink-50">{t(`admin.settingsMode_${value}`)}</span>
      </span>
    </label>
  )
}
