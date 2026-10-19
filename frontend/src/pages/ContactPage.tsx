import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { Reveal } from '../components/layout/Reveal'
import { Section } from '../components/layout/Section'
import { Button } from '../components/ui/Button'
import { StateMessage } from '../components/ui/StateMessage'
import { TextField, TextAreaField } from '../components/ui/Field'
import { useAuth } from '../features/auth/useAuth'
import { useI18n } from '../features/i18n/useI18n'
import { apiRequest, ApiError, errorMessage } from '../lib/api'
import { applyApiFieldErrors } from '../lib/formErrors'

type ContactValues = {
  name: string
  email: string
  subject: string
  body: string
}

export function ContactPage() {
  const { user } = useAuth()
  const { t } = useI18n()
  const [sent, setSent] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  const schema = useMemo(
    () =>
      z.object({
        name: z.string().min(2, t('common.validation.nameMin')).max(120),
        email: z.email(t('common.validation.email')).max(190),
        subject: z.string().min(2, t('contact.errors.subjectMin')).max(160),
        body: z
          .string()
          .min(10, t('contact.errors.bodyMin'))
          .max(5000, t('common.validation.textMax', { max: 5000 })),
      }),
    [t],
  )

  const form = useForm<ContactValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: user?.name ?? '',
      email: user?.email ?? '',
      subject: '',
      body: '',
    },
  })

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null)
    try {
      await apiRequest<{ message: string }>('/contact-messages', {
        method: 'POST',
        body: values,
      })
      setSent(true)
    } catch (error) {
      if (error instanceof ApiError && error.status === 429) {
        setFormError(t('contact.errors.rateLimited'))
        return
      }
      applyApiFieldErrors(
        error,
        (name, message) => form.setError(name as keyof ContactValues, { message }),
        setFormError,
        t('common.unexpectedError'),
      )
      if (!(error instanceof ApiError)) {
        setFormError(errorMessage(error, t('contact.errors.sendFailed')))
      }
    }
  })

  return (
    <article className="flex flex-col gap-0">
      <Section>
        <Reveal className="flex max-w-2xl flex-col gap-5">
          <h1 className="text-balance">{t('contact.title')}</h1>
          <p className="text-lg text-ink-70">{t('contact.lead')}</p>
          <p className="text-sm text-ink-50">{t('contact.note')}</p>
        </Reveal>

        <Reveal delay={0.1} className="mt-10 max-w-2xl">
          {sent ? (
            <StateMessage
              tone="success"
              title={t('contact.sentTitle')}
              description={t('contact.sentText')}
              action={
                <Button variant="secondary" size="sm" onClick={() => setSent(false)}>
                  {t('contact.sendAnother')}
                </Button>
              }
            />
          ) : (
            <form
              noValidate
              onSubmit={onSubmit}
              className="flex flex-col gap-4 rounded-card border border-hairline bg-surface p-6 sm:p-8"
            >
              {formError ? (
                <p role="alert" className="text-sm text-danger">
                  {formError}
                </p>
              ) : null}
              <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                  label={t('contact.name')}
                  autoComplete="name"
                  error={form.formState.errors.name?.message}
                  {...form.register('name')}
                />
                <TextField
                  label={t('auth.email')}
                  type="email"
                  autoComplete="email"
                  error={form.formState.errors.email?.message}
                  {...form.register('email')}
                />
              </div>
              <TextField
                label={t('contact.subject')}
                error={form.formState.errors.subject?.message}
                {...form.register('subject')}
              />
              <TextAreaField
                label={t('contact.body')}
                error={form.formState.errors.body?.message}
                {...form.register('body')}
              />
              <Button
                type="submit"
                disabled={form.formState.isSubmitting}
                className="w-fit"
              >
                {form.formState.isSubmitting ? t('contact.sending') : t('contact.send')}
              </Button>
            </form>
          )}
        </Reveal>
      </Section>
    </article>
  )
}
