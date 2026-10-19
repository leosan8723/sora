import { SelectField, TextField } from '../../components/ui/Field'
import { useI18n } from '../../features/i18n/useI18n'
import type { ConsultantSort, Specialty } from './types'

interface ConsultantFiltersProps {
  specialties: Specialty[]
  search: string
  specialty: string
  sort: ConsultantSort
  onSearchChange: (value: string) => void
  onSpecialtyChange: (value: string) => void
  onSortChange: (value: ConsultantSort) => void
}

export function ConsultantFilters({
  specialties,
  search,
  specialty,
  sort,
  onSearchChange,
  onSpecialtyChange,
  onSortChange,
}: ConsultantFiltersProps) {
  const { t } = useI18n()

  return (
    <section
      aria-label={t('consultants.filtersAria')}
      className="flex flex-col gap-4 rounded-card border border-hairline bg-surface p-5"
    >
      <div className="grid gap-4 md:grid-cols-[2fr_1fr]">
        <TextField
          label={t('consultants.search')}
          name="search"
          type="search"
          placeholder={t('consultants.searchPlaceholder')}
          value={search}
          onChange={(event) => onSearchChange(event.target.value)}
        />
        <SelectField
          label={t('consultants.sortBy')}
          name="sort"
          value={sort}
          onChange={(event) => onSortChange(event.target.value as ConsultantSort)}
        >
          <option value="name">{t('consultants.sortName')}</option>
          <option value="experience">{t('consultants.sortExperience')}</option>
          <option value="newest">{t('consultants.sortNewest')}</option>
        </SelectField>
      </div>

      <div className="flex flex-col gap-2">
        <span className="text-sm text-ink-70">{t('consultants.specialties')}</span>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            aria-pressed={specialty === ''}
            onClick={() => onSpecialtyChange('')}
            className={`rounded-control px-3 py-1.5 text-sm transition-colors ${
              specialty === ''
                ? 'bg-accent text-on-accent'
                : 'bg-surface-strong text-ink-70 hover:text-ink'
            }`}
          >
            {t('consultants.allSpecialties')}
          </button>
          {specialties.map((entry) => {
            const active = specialty === entry.slug
            return (
              <button
                key={entry.id}
                type="button"
                aria-pressed={active}
                onClick={() => onSpecialtyChange(entry.slug)}
                className={`rounded-control px-3 py-1.5 text-sm transition-colors ${
                  active
                    ? 'bg-accent text-on-accent'
                    : 'bg-surface-strong text-ink-70 hover:text-ink'
                }`}
              >
                {entry.name}
              </button>
            )
          })}
        </div>
      </div>
    </section>
  )
}
