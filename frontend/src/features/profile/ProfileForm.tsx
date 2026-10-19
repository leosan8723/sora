import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { Button } from '../../components/ui/Button'
import { TextField } from '../../components/ui/Field'
import { useI18n } from '../../features/i18n/useI18n'
import { applyApiFieldErrors } from '../../lib/formErrors'
import { useAuth } from '../auth/useAuth'
import { useUpdateProfile } from './useProfile'

type Values = {
  name: string
  phone?: string
}

export function ProfileForm() {
  const { refresh, user } = useAuth()
  const { t } = useI18n()
  const update = useUpdateProfile()
  const [formError, setFormError] = useState<string | null>(null)

  const schema = useMemo(
    () =>
      z.object({
        name: z
          .string()
          .min(2, t('common.validation.nameMin'))
          .max(120, t('profile.errors.nameMax')),
        phone: z
          .string()
          .max(20, t('common.validation.phoneMax'))
          .optional()
          .or(z.literal('')),
      }),
    [t],
  )

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: user?.name ?? '',
      phone: user?.phone ?? '',
    },
  })

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null)

    try {
      await update.mutateAsync({
        name: values.name.trim(),
        phone: values.phone?.trim() ? values.phone.trim() : null,
      })
      form.reset({ name: values.name.trim(), phone: values.phone ?? '' })
      await refresh()
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
        <h2 className="text-xl">{t('profile.basicsTitle')}</h2>
        <p className="text-sm text-ink-70">{t('profile.basicsText')}</p>
      </div>

      <form noValidate onSubmit={onSubmit} className="flex flex-col gap-4">
        {formError ? (
          <p role="alert" className="text-sm text-danger">
            {formError}
          </p>
        ) : null}

        <TextField
          label={t('auth.name')}
          autoComplete="name"
          error={form.formState.errors.name?.message}
          {...form.register('name')}
        />
        <TextField
          label={t('auth.phoneOptional')}
          inputMode="tel"
          autoComplete="tel"
          hint={t('profile.phoneHint')}
          error={form.formState.errors.phone?.message}
          {...form.register('phone')}
        />

        <div className="flex items-center gap-3">
          <Button type="submit" disabled={update.isPending}>
            {update.isPending ? t('profile.savingChanges') : t('dashboard.saveProfile')}
          </Button>
          {update.isSuccess && !form.formState.isDirty ? (
            <span className="text-sm text-accent">{t('dashboard.saved')}</span>
          ) : null}
        </div>
      </form>
    </section>
  )
}
