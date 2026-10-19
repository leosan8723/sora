import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { Button } from '../../components/ui/Button'
import { TextAreaField, TextField } from '../../components/ui/Field'
import { useI18n } from '../../features/i18n/useI18n'
import { applyApiFieldErrors } from '../../lib/formErrors'
import type { ConsultationTypePayload, ManagedConsultationType } from './types'
import { useCreateOwnerType, useUpdateOwnerType } from './useConsultantDashboard'

type Values = {
  title: string
  description: string
  duration_minutes: string
  price_toman: string
  price_usd: string
  is_active: boolean
}

interface ConsultationTypeFormProps {
  initial?: ManagedConsultationType
  onDone: () => void
  onCancel: () => void
}

export function ConsultationTypeForm({
  initial,
  onDone,
  onCancel,
}: ConsultationTypeFormProps) {
  const { t } = useI18n()
  const createMutation = useCreateOwnerType()
  const updateMutation = useUpdateOwnerType()
  const [formError, setFormError] = useState<string | null>(null)

  const schema = useMemo(
    () =>
      z.object({
        title: z.string().min(2, t('common.validation.required')).max(120),
        description: z.string().max(4000, t('common.validation.textMax', { max: 4000 })),
        duration_minutes: z
          .string()
          .refine(
            (value) =>
              /^\d+$/.test(value) && Number(value) >= 5 && Number(value) <= 600,
            t('dashboard.durationRange'),
          ),
        price_toman: z
          .string()
          .refine(
            (value) => /^\d+$/.test(value) && Number(value) >= 0,
            t('dashboard.priceRange'),
          ),
        // Optional USD fee in dollars; empty clears it. Stored as integer
        // cents — no conversion from toman is ever invented client-side.
        price_usd: z
          .string()
          .refine(
            (value) => value === '' || (/^\d+(\.\d{1,2})?$/.test(value) && Number(value) >= 0),
            t('dashboard.usdRange'),
          ),
        is_active: z.boolean(),
      }),
    [t],
  )

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      title: initial?.title ?? '',
      description: initial?.description ?? '',
      duration_minutes: initial ? String(initial.duration_minutes) : '60',
      price_toman: initial ? String(initial.price_toman) : '',
      price_usd:
        initial?.price_usd_cents == null ? '' : String(initial.price_usd_cents / 100),
      is_active: initial?.is_active ?? true,
    },
  })

  const pending = createMutation.isPending || updateMutation.isPending

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null)
    const payload: ConsultationTypePayload = {
      title: values.title.trim(),
      description: values.description.trim() || null,
      duration_minutes: Number(values.duration_minutes),
      price_toman: Number(values.price_toman),
      price_usd_cents:
        values.price_usd === ''
          ? null
          : Math.round(Number(values.price_usd) * 100),
      is_active: values.is_active,
    }

    try {
      if (initial) {
        await updateMutation.mutateAsync({ id: initial.id, payload })
      } else {
        await createMutation.mutateAsync(payload)
      }
      onDone()
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
    <form
      noValidate
      onSubmit={onSubmit}
      className="flex flex-col gap-4 rounded-panel border border-hairline bg-canvas/40 p-4"
    >
      {formError ? (
        <p role="alert" className="text-sm text-danger">
          {formError}
        </p>
      ) : null}

      <TextField
        label={t('dashboard.typeTitle')}
        placeholder={t('dashboard.typeTitlePlaceholder')}
        error={form.formState.errors.title?.message}
        {...form.register('title')}
      />
      <TextAreaField
        label={t('dashboard.typeDescription')}
        error={form.formState.errors.description?.message}
        {...form.register('description')}
      />
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField
          label={t('dashboard.durationLabel')}
          inputMode="numeric"
          error={form.formState.errors.duration_minutes?.message}
          {...form.register('duration_minutes')}
        />
        <TextField
          label={t('dashboard.priceLabel')}
          inputMode="numeric"
          error={form.formState.errors.price_toman?.message}
          {...form.register('price_toman')}
        />
      </div>
      <TextField
        label={t('dashboard.usdLabel')}
        hint={t('dashboard.usdHint')}
        inputMode="decimal"
        dir="ltr"
        error={form.formState.errors.price_usd?.message}
        {...form.register('price_usd')}
      />

      <label className="flex items-center gap-2 text-sm text-ink-70">
        <input type="checkbox" className="size-4 accent-accent" {...form.register('is_active')} />
        {t('dashboard.typeActive')}
      </label>

      <div className="flex items-center gap-3">
        <Button type="submit" size="sm" disabled={pending}>
          {pending
            ? t('common.saving')
            : initial
              ? t('dashboard.saveChanges')
              : t('dashboard.addType')}
        </Button>
        <Button variant="ghost" size="sm" onClick={onCancel} disabled={pending}>
          {t('common.cancel')}
        </Button>
      </div>
    </form>
  )
}
