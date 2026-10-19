import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { Button } from '../../components/ui/Button'
import { TextAreaField, TextField } from '../../components/ui/Field'
import { useI18n } from '../../features/i18n/useI18n'
import { applyApiFieldErrors } from '../../lib/formErrors'
import type { OwnerProfile, ProfilePayload } from './types'
import { useUpdateOwnerProfile } from './useConsultantDashboard'

type Values = {
  headline: string
  bio: string
  years_experience: string
  name_en: string
  headline_en: string
  bio_en: string
}

export function ConsultantProfileForm({ profile }: { profile: OwnerProfile }) {
  const { t } = useI18n()
  const mutation = useUpdateOwnerProfile()
  const [formError, setFormError] = useState<string | null>(null)

  const schema = useMemo(
    () =>
      z.object({
        headline: z
          .string()
          .max(160, t('dashboard.headlineMax')),
        bio: z.string().max(4000, t('common.validation.textMax', { max: 4000 })),
        years_experience: z
          .string()
          .refine(
            (value) => value === '' || /^\d+$/.test(value),
            t('common.validation.numberRequired'),
          )
          .refine((value) => value === '' || Number(value) <= 80, t('dashboard.experienceMax')),
        name_en: z.string().max(160, t('dashboard.nameEnMax')),
        headline_en: z.string().max(160, t('dashboard.headlineMax')),
        bio_en: z.string().max(4000, t('common.validation.textMax', { max: 4000 })),
      }),
    [t],
  )

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      headline: profile.headline ?? '',
      bio: profile.bio ?? '',
      years_experience:
        profile.years_experience === null
          ? ''
          : String(profile.years_experience),
      name_en: profile.name_en ?? '',
      headline_en: profile.headline_en ?? '',
      bio_en: profile.bio_en ?? '',
    },
  })

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null)
    const payload: ProfilePayload = {
      headline: values.headline.trim() || null,
      bio: values.bio.trim() || null,
      years_experience: values.years_experience === '' ? null : Number(values.years_experience),
      name_en: values.name_en.trim() || null,
      headline_en: values.headline_en.trim() || null,
      bio_en: values.bio_en.trim() || null,
    }

    try {
      await mutation.mutateAsync(payload)
      form.reset(values)
    } catch (error) {
      applyApiFieldErrors(
        error,
        (name, message) => form.setError(name as keyof Values, { message }),
        setFormError,
        t('common.unexpectedError'),
      )
    }
  })

  return (
    <section className="flex flex-col gap-4 rounded-card border border-hairline bg-surface p-6">
      <div className="flex flex-col gap-1">
        <h2 className="text-xl">{t('dashboard.profileTitle')}</h2>
        <p className="text-sm text-ink-70">{t('dashboard.profileText')}</p>
      </div>

      <form noValidate onSubmit={onSubmit} className="flex flex-col gap-4">
        {formError ? (
          <p role="alert" className="text-sm text-danger">
            {formError}
          </p>
        ) : null}

        <TextField
          label={t('dashboard.headlineLabel')}
          placeholder={t('dashboard.headlinePlaceholder')}
          error={form.formState.errors.headline?.message}
          {...form.register('headline')}
        />
        <TextAreaField
          label={t('dashboard.bioLabel')}
          placeholder={t('dashboard.bioPlaceholder')}
          error={form.formState.errors.bio?.message}
          {...form.register('bio')}
        />
        <TextField
          label={t('dashboard.experienceLabel')}
          inputMode="numeric"
          error={form.formState.errors.years_experience?.message}
          {...form.register('years_experience')}
        />

        <div className="flex flex-col gap-1 border-t border-hairline pt-3">
          <h3 className="text-sm text-ink-70">{t('dashboard.englishContentTitle')}</h3>
          <p className="text-xs text-ink-50">{t('dashboard.englishContentHint')}</p>
        </div>
        <TextField
          label={t('dashboard.nameEnLabel')}
          dir="ltr"
          error={form.formState.errors.name_en?.message}
          {...form.register('name_en')}
        />
        <TextField
          label={t('dashboard.headlineEnLabel')}
          dir="ltr"
          error={form.formState.errors.headline_en?.message}
          {...form.register('headline_en')}
        />
        <TextAreaField
          label={t('dashboard.bioEnLabel')}
          dir="ltr"
          error={form.formState.errors.bio_en?.message}
          {...form.register('bio_en')}
        />

        <div className="flex items-center gap-3">
          <Button type="submit" disabled={mutation.isPending}>
            {mutation.isPending ? t('common.saving') : t('dashboard.saveProfile')}
          </Button>
          {mutation.isSuccess && !form.formState.isDirty ? (
            <span className="text-sm text-accent">{t('dashboard.saved')}</span>
          ) : null}
        </div>
      </form>
    </section>
  )
}
