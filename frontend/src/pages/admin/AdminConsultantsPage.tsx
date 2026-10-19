import { useState } from 'react'

import { Badge } from '../../components/ui/Badge'
import { Button } from '../../components/ui/Button'
import { Pagination } from '../../components/ui/Pagination'
import { Skeleton } from '../../components/ui/Skeleton'
import { StateMessage } from '../../components/ui/StateMessage'
import { TextAreaField, TextField } from '../../components/ui/Field'
import {
  useAdminConsultants,
  useUpdateAdminConsultant,
} from '../../features/admin/useAdmin'
import type { AdminConsultant } from '../../features/admin/types'
import { useI18n } from '../../features/i18n/useI18n'
import { errorMessage } from '../../lib/api'

function EnglishContentEditor({ consultant }: { consultant: AdminConsultant }) {
  const { t } = useI18n()
  const update = useUpdateAdminConsultant()
  const [open, setOpen] = useState(false)
  const [nameEn, setNameEn] = useState(consultant.name_en ?? '')
  const [headlineEn, setHeadlineEn] = useState(consultant.headline_en ?? '')
  const [bioEn, setBioEn] = useState(consultant.bio_en ?? '')
  const [error, setError] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)

  const missing = consultant.translation_missing_en ?? []

  function save() {
    setSaved(false)
    setError(null)
    update.mutate(
      {
        id: consultant.id,
        payload: {
          name_en: nameEn === '' ? null : nameEn,
          headline_en: headlineEn === '' ? null : headlineEn,
          bio_en: bioEn === '' ? null : bioEn,
        },
      },
      {
        onSuccess: () => setSaved(true),
        onError: (cause) =>
          setError(errorMessage(cause, t('admin.errors.consultantUpdateFailed'))),
      },
    )
  }

  return (
    <div className="flex flex-col gap-3">
      <div>
        <Button variant="secondary" size="sm" onClick={() => setOpen(!open)}>
          {open ? t('admin.englishContentHide') : t('admin.englishContentShow')}
        </Button>
        {missing.length > 0 ? (
          <span className="ms-2 inline-flex items-center">
            <Badge tone="accent">{t('admin.translationMissing', { count: missing.length })}</Badge>
          </span>
        ) : null}
      </div>

      {open ? (
        <div className="flex flex-col gap-3 rounded-panel border border-dashed border-hairline bg-surface-soft p-4">
          <TextField
            label={t('admin.nameEn')}
            value={nameEn}
            onChange={(event) => setNameEn(event.target.value)}
            dir="ltr"
            maxLength={160}
          />
          <TextField
            label={t('admin.headlineEn')}
            value={headlineEn}
            onChange={(event) => setHeadlineEn(event.target.value)}
            dir="ltr"
            maxLength={160}
          />
          <TextAreaField
            label={t('admin.bioEn')}
            value={bioEn}
            onChange={(event) => setBioEn(event.target.value)}
            dir="ltr"
            rows={4}
            maxLength={4000}
          />
          {error ? (
            <p role="alert" className="text-sm text-danger">
              {error}
            </p>
          ) : null}
          <div className="flex items-center gap-3">
            <Button size="sm" disabled={update.isPending} onClick={save}>
              {update.isPending ? t('common.saving') : t('admin.englishContentSave')}
            </Button>
            {saved ? (
              <span role="status" className="text-sm text-ink-70">
                {t('admin.englishContentSaved')}
              </span>
            ) : null}
          </div>
        </div>
      ) : null}
    </div>
  )
}

export function AdminConsultantsPage() {
  const { t, fmt } = useI18n()
  const [query, setQuery] = useState('')
  const [page, setPage] = useState(1)
  const consultants = useAdminConsultants({ q: query || undefined, page })
  const update = useUpdateAdminConsultant()
  const [error, setError] = useState<string | null>(null)

  function patch(target: AdminConsultant, payload: Partial<AdminConsultant>) {
    setError(null)
    update.mutate(
      { id: target.id, payload },
      { onError: (cause) => setError(errorMessage(cause, t('admin.errors.consultantUpdateFailed'))) },
    )
  }

  return (
    <div className="flex flex-col gap-5">
      <div className="w-full max-w-xs">
        <TextField
          label={t('admin.searchByName')}
          value={query}
          onChange={(event) => {
            setQuery(event.target.value)
            setPage(1)
          }}
        />
      </div>

      {error ? (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      ) : null}

      {consultants.isPending ? (
        <div className="flex flex-col gap-3" aria-hidden="true">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-16 w-full rounded-card" />
          ))}
        </div>
      ) : consultants.isError ? (
        <StateMessage tone="error" title={t('admin.errors.consultantsLoadFailed')} />
      ) : consultants.data.data.length === 0 ? (
        <StateMessage title={t('admin.noConsultants')} />
      ) : (
        <ul className="flex flex-col gap-3">
          {consultants.data.data.map((consultant) => (
            <li
              key={consultant.id}
              className="flex flex-col gap-3 rounded-card border border-hairline bg-surface p-5"
            >
              <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex min-w-0 flex-col gap-1">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="text-ink">{consultant.user?.name ?? '—'}</span>
                  {consultant.is_verified ? (
                    <Badge tone="success">{t('admin.verified')}</Badge>
                  ) : (
                    <Badge tone="muted">{t('admin.unverified')}</Badge>
                  )}
                  {!consultant.is_active ? <Badge tone="muted">{t('admin.deactivated')}</Badge> : null}
                </div>
                <p className="truncate text-sm text-ink-50">
                  {consultant.user?.email}
                  {consultant.years_experience !== null
                    ? ` — ${t('consultants.experience', { years: fmt.number(consultant.years_experience) })}`
                    : ''}
                </p>
                <p className="text-sm text-ink-30">
                  {t('admin.completedSessions', {
                    count: fmt.number(consultant.appointments_count ?? 0),
                  })}
                </p>
              </div>
              <div className="flex shrink-0 flex-wrap gap-2">
                <Button
                  variant={consultant.is_verified ? 'secondary' : 'primary'}
                  size="sm"
                  disabled={update.isPending}
                  onClick={() =>
                    patch(consultant, { is_verified: !consultant.is_verified })
                  }
                >
                  {consultant.is_verified ? t('admin.unverifyBadge') : t('admin.verifyConsultant')}
                </Button>
                <Button
                  variant={consultant.is_active ? 'danger' : 'secondary'}
                  size="sm"
                  disabled={update.isPending}
                  onClick={() =>
                    patch(consultant, { is_active: !consultant.is_active })
                  }
                >
                  {consultant.is_active ? t('admin.deactivate') : t('admin.activate')}
                </Button>
              </div>
              </div>
              <EnglishContentEditor consultant={consultant} />
            </li>
          ))}
        </ul>
      )}

      {consultants.data ? (
        <Pagination
          page={consultants.data.meta.current_page}
          lastPage={consultants.data.meta.last_page}
          total={consultants.data.meta.total}
          onPageChange={setPage}
        />
      ) : null}
    </div>
  )
}
