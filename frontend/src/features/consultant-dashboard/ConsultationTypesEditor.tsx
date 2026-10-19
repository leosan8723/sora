import { useState } from 'react'

import { Badge } from '../../components/ui/Badge'
import { Button } from '../../components/ui/Button'
import { Skeleton } from '../../components/ui/Skeleton'
import { StateMessage } from '../../components/ui/StateMessage'
import { useI18n } from '../../features/i18n/useI18n'
import { errorMessage } from '../../lib/api'
import { ConsultationTypeForm } from './ConsultationTypeForm'
import { useDeleteOwnerType, useOwnerTypes } from './useConsultantDashboard'

export function ConsultationTypesEditor() {
  const { t, fmt } = useI18n()
  const types = useOwnerTypes()
  const remove = useDeleteOwnerType()
  const [creating, setCreating] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [pendingDeleteId, setPendingDeleteId] = useState<number | null>(null)

  const items = types.data ?? []

  return (
    <section className="flex flex-col gap-4 rounded-card border border-hairline bg-surface p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-col gap-1">
          <h2 className="text-xl">{t('dashboard.typeEditorTitle')}</h2>
          <p className="text-sm text-ink-70">{t('dashboard.typeEditorText')}</p>
        </div>
        {!creating ? (
          <Button size="sm" onClick={() => setCreating(true)}>
            {t('dashboard.addType')}
          </Button>
        ) : null}
      </div>

      {creating ? (
        <ConsultationTypeForm
          onDone={() => setCreating(false)}
          onCancel={() => setCreating(false)}
        />
      ) : null}

      {types.isPending ? (
        <div className="flex flex-col gap-3" aria-hidden="true">
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-16 w-full" />
        </div>
      ) : types.isError ? (
        <StateMessage
          tone="error"
          title={t('dashboard.typesFailed')}
          description={errorMessage(types.error, t('common.networkError'))}
          action={
            <Button variant="secondary" size="sm" onClick={() => types.refetch()}>
              {t('common.retry')}
            </Button>
          }
        />
      ) : items.length === 0 && !creating ? (
        <p className="rounded-panel border border-dashed border-hairline p-4 text-sm text-ink-50">
          {t('dashboard.noTypesYet')}
        </p>
      ) : (
        <ul className="flex flex-col gap-3">
          {items.map((type) =>
            editingId === type.id ? (
              <li key={type.id}>
                <ConsultationTypeForm
                  initial={type}
                  onDone={() => setEditingId(null)}
                  onCancel={() => setEditingId(null)}
                />
              </li>
            ) : (
              <li
                key={type.id}
                className="flex flex-wrap items-center justify-between gap-3 rounded-panel border border-hairline bg-canvas/40 p-4"
              >
                <div className="flex flex-col gap-1">
                  <div className="flex items-center gap-2">
                    <span className="text-ink">{type.title}</span>
                    {type.is_active ? (
                      <Badge tone="accent">{t('dashboard.active')}</Badge>
                    ) : (
                      <Badge tone="muted">{t('dashboard.inactive')}</Badge>
                    )}
                  </div>
                  <span className="text-sm text-ink-50">
                    {fmt.duration(type.duration_minutes)} — {fmt.toman(type.price_toman)}
                  </span>
                </div>

                {pendingDeleteId === type.id ? (
                  <div className="flex items-center gap-2">
                    <span className="text-sm text-ink-70">{t('dashboard.deletePrompt')}</span>
                    <Button
                      variant="danger"
                      size="sm"
                      disabled={remove.isPending}
                      onClick={() => {
                        remove.mutate(type.id, {
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
                      onClick={() => setEditingId(type.id)}
                    >
                      {t('common.edit')}
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => setPendingDeleteId(type.id)}
                    >
                      {t('common.delete')}
                    </Button>
                  </div>
                )}
              </li>
            ),
          )}
        </ul>
      )}
    </section>
  )
}
