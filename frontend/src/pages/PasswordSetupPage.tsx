import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useSearchParams } from 'react-router'
import { z } from 'zod'

import { Button } from '../components/ui/Button'
import { TextField } from '../components/ui/Field'
import { useI18n } from '../features/i18n/useI18n'
import { apiRequest } from '../lib/api'
import { applyApiFieldErrors } from '../lib/formErrors'

type SetupValues = z.infer<ReturnType<typeof buildSetupSchema>>

function buildSetupSchema(translate: (path: string) => string) {
  return z
    .object({
      email: z.string().email(translate('common.validation.email')),
      password: z
        .string()
        .min(12, translate('auth.passwordSetup.tooShort'))
        .max(72),
      password_confirmation: z.string().max(72),
    })
    .refine(
      (values) => values.password === values.password_confirmation,
      {
        path: ['password_confirmation'],
        message: translate('common.validation.passwordMismatch'),
      },
    )
}

export function PasswordSetupPage() {
  const { t } = useI18n()
  const [params] = useSearchParams()
  const [state, setState] = useState<'form' | 'done'>('form')
  const [formError, setFormError] = useState<string | null>(null)

  const token = params.get('token') ?? ''
  const email = params.get('email') ?? ''

  const form = useForm<SetupValues>({
    resolver: zodResolver(
      useMemo(() => buildSetupSchema((path) => t(path)), [t]),
    ),
    defaultValues: { email, password: '', password_confirmation: '' },
  })

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null)
    try {
      await apiRequest<{ message: string }>('/auth/password-setup', {
        method: 'POST',
        body: {
          email: values.email,
          token,
          password: values.password,
          password_confirmation: values.password_confirmation,
        },
      })
      setState('done')
    } catch (error) {
      applyApiFieldErrors(
        error,
        (name, message) => form.setError(name as keyof SetupValues, { message }),
        setFormError,
        t('common.unexpectedError'),
      )
    }
  })

  if (!token || !email) {
    return (
      <div className="mx-auto flex w-full max-w-md flex-col justify-center gap-6 py-8">
        <div className="flex flex-col gap-2 rounded-card border border-hairline bg-surface p-6">
          <h1 className="text-2xl">{t('auth.passwordSetup.title')}</h1>
          <p className="text-sm text-ink-70">{t('auth.passwordSetup.invalidLink')}</p>
          <nav aria-label={t('auth.passwordSetup.actions')}>
            <Link to="/login" className="text-sm underline hover:text-ink">
              {t('nav.login')}
            </Link>
          </nav>
        </div>
      </div>
    )
  }

  return (
    <div className="mx-auto flex w-full max-w-md flex-col justify-center gap-6 py-8">
      <Link to="/" className="text-sm text-ink-50 transition-colors hover:text-ink">
        {t('auth.backHome')}
      </Link>

      <div className="flex flex-col gap-1">
        <h1 className="text-2xl">{t('auth.passwordSetup.title')}</h1>
        <p className="text-sm text-ink-70">{t('auth.passwordSetup.subtitle')}</p>
      </div>

      {state === 'done' ? (
        <div className="flex flex-col gap-3 rounded-card border border-hairline bg-surface p-6">
          <p role="status" className="text-sm text-ink">
            {t('auth.passwordSetup.success')}
          </p>
          <Link to="/login" className="text-sm underline hover:text-ink">
            {t('auth.passwordSetup.goToLogin')}
          </Link>
        </div>
      ) : (
        <form
          noValidate
          onSubmit={onSubmit}
          className="flex flex-col gap-4 rounded-card border border-hairline bg-surface p-6"
        >
          {formError ? (
            <p role="alert" className="text-sm text-danger">
              {formError}
            </p>
          ) : null}
          <TextField
            label={t('auth.email')}
            type="email"
            autoComplete="email"
            dir="ltr"
            error={form.formState.errors.email?.message}
            {...form.register('email')}
          />
          <TextField
            label={t('auth.password')}
            type="password"
            autoComplete="new-password"
            hint={t('auth.passwordSetup.hint')}
            error={form.formState.errors.password?.message}
            {...form.register('password')}
          />
          <TextField
            label={t('auth.passwordConfirmation')}
            type="password"
            autoComplete="new-password"
            error={form.formState.errors.password_confirmation?.message}
            {...form.register('password_confirmation')}
          />
          <Button type="submit" disabled={form.formState.isSubmitting}>
            {form.formState.isSubmitting
              ? t('auth.submittingLogin')
              : t('auth.passwordSetup.submit')}
          </Button>
        </form>
      )}
    </div>
  )
}
