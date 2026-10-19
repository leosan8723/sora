import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm } from 'react-hook-form'
import { useMemo, useState } from 'react'
import { z } from 'zod'

import { Button } from '../../components/ui/Button'
import { SelectField, TextAreaField } from '../../components/ui/Field'
import { useI18n } from '../../features/i18n/useI18n'
import { ApiError } from '../../lib/api'
import { applyApiFieldErrors } from '../../lib/formErrors'
import { useSubmitReview, useUpdateReview } from './useReviews'
import { StarRatingInput } from './StarRating'
import type { Review, ReviewableAppointment } from './types'

type ReviewValues = {
  appointment_id: string
  rating: number
  body: string
}

interface ReviewFormProps {
  consultantId: number
  /** Completed, not-yet-reviewed visits; empty when the client cannot review. */
  appointments: ReviewableAppointment[]
  /** Set when the author is editing one of their own reviews. */
  editing?: Review | null
  onDone: () => void
  onCancel: () => void
}

/**
 * Review editor. New reviews are anchored to a completed appointment — the
 * server rejects anything else, so the appointment selector only lists visits
 * the API has already confirmed as reviewable.
 */
export function ReviewForm({
  consultantId,
  appointments,
  editing = null,
  onDone,
  onCancel,
}: ReviewFormProps) {
  const { t, fmt } = useI18n()
  const [formError, setFormError] = useState<string | null>(null)
  const submitReview = useSubmitReview(consultantId)
  const updateReview = useUpdateReview(consultantId)

  const schema = useMemo(
    () =>
      z.object({
        appointment_id: z.string(),
        rating: z.number().int().min(1, t('common.validation.ratingRequired')).max(5),
        body: z
          .string()
          .min(10, t('reviews.errors.bodyMin'))
          .max(1000, t('common.validation.textMax', { max: 1000 })),
      }),
    [t],
  )

  const form = useForm<ReviewValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      appointment_id: appointments[0] ? String(appointments[0].id) : '',
      rating: editing?.rating ?? 0,
      body: editing?.body ?? '',
    },
  })

  const isPending = submitReview.isPending || updateReview.isPending

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null)

    const onError = (error: unknown) => {
      if (error instanceof ApiError && error.status === 429) {
        setFormError(t('reviews.errors.rateLimited'))
        return
      }
      applyApiFieldErrors(
        error,
        (name, message) => form.setError(name as keyof ReviewValues, { message }),
        setFormError,
        t('common.unexpectedError'),
      )
    }

    try {
      if (editing) {
        await updateReview.mutateAsync({
          id: editing.id,
          payload: { rating: values.rating, body: values.body },
        })
      } else {
        await submitReview.mutateAsync({
          appointment_id: Number(values.appointment_id),
          rating: values.rating,
          body: values.body,
        })
      }
      onDone()
    } catch (error) {
      onError(error)
    }
  })

  const ratingError = form.formState.errors.rating?.message

  return (
    <form
      onSubmit={onSubmit}
      className="flex flex-col gap-4 rounded-card border border-hairline bg-surface p-5"
      noValidate
    >
      <h3 className="text-base text-ink">
        {editing ? t('reviews.editTitle') : t('reviews.writeTitle')}
      </h3>

      <p className="text-sm text-ink-50">
        {editing ? t('reviews.editNotice') : t('reviews.writeNotice')}
      </p>

      {!editing ? (
        appointments.length === 0 ? (
          <p className="text-sm text-ink-50">{t('reviews.noReviewable')}</p>
        ) : (
          <SelectField
            label={t('reviews.chooseSession')}
            error={form.formState.errors.appointment_id?.message}
            {...form.register('appointment_id')}
          >
            {appointments.map((appointment) => (
              <option key={appointment.id} value={appointment.id}>
                {appointment.starts_at ? fmt.date(appointment.starts_at) : t('reviews.sessionId', { id: appointment.id })}
                {appointment.consultation_type_title
                  ? ` — ${appointment.consultation_type_title}`
                  : ''}
              </option>
            ))}
          </SelectField>
        )
      ) : null}

      <Controller
        control={form.control}
        name="rating"
        render={({ field }) => (
          <div className="flex flex-col gap-1.5">
            <StarRatingInput
              name="rating"
              value={field.value}
              onChange={field.onChange}
              disabled={isPending}
            />
            {ratingError ? (
              <p className="text-sm text-danger">{ratingError}</p>
            ) : null}
          </div>
        )}
      />

      <TextAreaField
        label={t('reviews.bodyLabel')}
        rows={5}
        error={form.formState.errors.body?.message}
        hint={t('reviews.bodyHint')}
        {...form.register('body')}
      />

      {formError ? <p className="text-sm text-danger">{formError}</p> : null}

      <div className="flex flex-wrap items-center gap-3">
        <Button type="submit" disabled={isPending || (!editing && appointments.length === 0)}>
          {isPending
            ? t('contact.sending')
            : editing
              ? t('reviews.saveEdit')
              : t('reviews.submitReview')}
        </Button>
        <Button type="button" variant="ghost" onClick={onCancel} disabled={isPending}>
          {t('common.cancel')}
        </Button>
      </div>
    </form>
  )
}
