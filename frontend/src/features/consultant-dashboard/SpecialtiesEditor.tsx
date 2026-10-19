import { useState } from 'react'

import { Button } from '../../components/ui/Button'
import { useI18n } from '../../features/i18n/useI18n'
import type { Specialty } from '../consultants/types'
import { useSyncOwnerSpecialties } from './useConsultantDashboard'

interface SpecialtiesEditorProps {
  all: Specialty[]
  selected: number[]
}

export function SpecialtiesEditor({ all, selected }: SpecialtiesEditorProps) {
  const { t } = useI18n()
  const mutation = useSyncOwnerSpecialties()

  // Re-derive the local selection whenever the saved selection changes, without
  // an effect (React's "adjust state during render" pattern).
  const signature = selected.join(',')
  const [local, setLocal] = useState({ signature, chosen: selected })

  if (local.signature !== signature) {
    setLocal({ signature, chosen: selected })
  }

  const chosen = local.chosen

  const changed =
    chosen.length !== selected.length ||
    chosen.some((id) => !selected.includes(id))

  function toggle(id: number) {
    setLocal({
      signature,
      chosen: chosen.includes(id)
        ? chosen.filter((value) => value !== id)
        : [...chosen, id],
    })
  }

  return (
    <section className="flex flex-col gap-4 rounded-card border border-hairline bg-surface p-6">
      <div className="flex flex-col gap-1">
        <h2 className="text-xl">{t('dashboard.specialtiesTitle')}</h2>
        <p className="text-sm text-ink-70">{t('dashboard.specialtiesText')}</p>
      </div>

      {all.length === 0 ? (
        <p className="text-sm text-ink-50">{t('dashboard.noSpecialties')}</p>
      ) : (
        <ul className="flex flex-wrap gap-2">
          {all.map((specialty) => {
            const active = chosen.includes(specialty.id)
            return (
              <li key={specialty.id}>
                <button
                  type="button"
                  aria-pressed={active}
                  onClick={() => toggle(specialty.id)}
                  className={`rounded-control px-3 py-1.5 text-sm transition-colors ${
                    active
                      ? 'bg-accent text-on-accent'
                      : 'bg-surface-strong text-ink-70 hover:text-ink'
                  }`}
                >
                  {specialty.name}
                </button>
              </li>
            )
          })}
        </ul>
      )}

      <div className="flex items-center gap-3">
        <Button
          variant="secondary"
          disabled={!changed || mutation.isPending}
          onClick={() => mutation.mutate(chosen)}
        >
          {mutation.isPending ? t('common.saving') : t('dashboard.saveSpecialties')}
        </Button>
        {mutation.isSuccess && !changed ? (
          <span className="text-sm text-accent">{t('dashboard.saved')}</span>
        ) : null}
      </div>
    </section>
  )
}
