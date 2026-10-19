import { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router'

import { Button } from '../components/ui/Button'
import { Pagination } from '../components/ui/Pagination'
import { Skeleton } from '../components/ui/Skeleton'
import { StateMessage } from '../components/ui/StateMessage'
import { ConsultantCard } from '../features/consultants/ConsultantCard'
import { ConsultantFilters } from '../features/consultants/ConsultantFilters'
import type { ConsultantListParams, ConsultantSort } from '../features/consultants/types'
import { useConsultants, useSpecialties } from '../features/consultants/useConsultants'
import { useI18n } from '../features/i18n/useI18n'
import { errorMessage } from '../lib/api'
import { useDebouncedValue } from '../lib/useDebouncedValue'

const SORTS: ConsultantSort[] = ['name', 'experience', 'newest']

function readSort(value: string | null): ConsultantSort {
  return SORTS.includes(value as ConsultantSort)
    ? (value as ConsultantSort)
    : 'name'
}

export function ConsultantsPage() {
  const { t, fmt } = useI18n()
  const [searchParams, setSearchParams] = useSearchParams()

  const q = searchParams.get('q') ?? ''
  const specialty = searchParams.get('specialty') ?? ''
  const sort = readSort(searchParams.get('sort'))
  const page = Math.max(1, Number(searchParams.get('page') ?? '1') || 1)

  const [searchInput, setSearchInput] = useState(q)
  const debouncedSearch = useDebouncedValue(searchInput, 350)

  const updateParams = useCallback(
    (patch: Record<string, string | undefined>) => {
      setSearchParams(
        (previous) => {
          const next = new URLSearchParams(previous)
          for (const [key, value] of Object.entries(patch)) {
            if (value === undefined || value === '') {
              next.delete(key)
            } else {
              next.set(key, value)
            }
          }
          return next
        },
        { replace: true },
      )
    },
    [setSearchParams],
  )

  useEffect(() => {
    if (debouncedSearch === q) {
      return
    }
    updateParams({ q: debouncedSearch || undefined, page: undefined })
  }, [debouncedSearch, q, updateParams])

  const specialties = useSpecialties()
  const params: ConsultantListParams = {
    q: q || undefined,
    specialty: specialty || undefined,
    sort,
    page,
  }
  const consultants = useConsultants(params)

  const results = consultants.data?.data ?? []
  const meta = consultants.data?.meta
  const hasFilters = q !== '' || specialty !== ''

  return (
    <div className="flex flex-col gap-8">
      <header className="flex flex-col gap-2">
        <h1 className="text-3xl">{t('consultants.title')}</h1>
        <p className="max-w-2xl text-ink-70">{t('consultants.subtitle')}</p>
      </header>

      <ConsultantFilters
        specialties={specialties.data ?? []}
        search={searchInput}
        specialty={specialty}
        sort={sort}
        onSearchChange={setSearchInput}
        onSpecialtyChange={(value) =>
          updateParams({ specialty: value || undefined, page: undefined })
        }
        onSortChange={(value) =>
          updateParams({ sort: value, page: undefined })
        }
      />

      {consultants.isPending ? (
        <ResultsSkeleton />
      ) : consultants.isError ? (
        <StateMessage
          tone="error"
          title={t('consultants.loadFailed')}
          description={errorMessage(consultants.error, t('common.networkError'))}
          action={
            <Button variant="secondary" size="sm" onClick={() => consultants.refetch()}>
              {t('common.retry')}
            </Button>
          }
        />
      ) : results.length === 0 ? (
        <StateMessage
          title={t('consultants.noneFound')}
          description={
            hasFilters ? t('consultants.emptyFiltered') : t('consultants.empty')
          }
          action={
            hasFilters ? (
              <Button
                variant="secondary"
                size="sm"
                onClick={() => {
                  setSearchInput('')
                  setSearchParams(new URLSearchParams(), { replace: true })
                }}
              >
                {t('consultants.clearFilters')}
              </Button>
            ) : undefined
          }
        />
      ) : (
        <div className="flex flex-col gap-6">
          {meta ? (
            <p className="text-sm text-ink-50" aria-live="polite">
              {t('consultants.foundCount', { count: fmt.number(meta.total) })}
            </p>
          ) : null}
          <ul
            className={`grid gap-5 sm:grid-cols-2 lg:grid-cols-3 ${
              consultants.isFetching ? 'opacity-60 transition-opacity' : ''
            }`}
          >
            {results.map((consultant) => (
              <li key={consultant.id}>
                <ConsultantCard consultant={consultant} />
              </li>
            ))}
          </ul>
          {meta ? (
            <Pagination
              page={meta.current_page}
              lastPage={meta.last_page}
              total={meta.total}
              onPageChange={(next) =>
                updateParams({ page: next > 1 ? String(next) : undefined })
              }
            />
          ) : null}
        </div>
      )}
    </div>
  )
}

function ResultsSkeleton() {
  return (
    <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3" aria-hidden="true">
      {Array.from({ length: 6 }, (_, index) => (
        <li
          key={index}
          className="flex flex-col gap-4 rounded-card border border-hairline bg-surface p-5"
        >
          <div className="flex items-center gap-4">
            <Skeleton className="size-12 rounded-panel" />
            <div className="flex flex-1 flex-col gap-2">
              <Skeleton className="h-4 w-2/3" />
              <Skeleton className="h-3 w-full" />
            </div>
          </div>
          <Skeleton className="h-6 w-1/2" />
          <Skeleton className="h-4 w-1/3" />
        </li>
      ))}
    </ul>
  )
}
