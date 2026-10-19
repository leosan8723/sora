import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { Button } from '../../components/ui/Button'
import { TextField } from '../../components/ui/Field'
import { useI18n } from '../../features/i18n/useI18n'
import { applyApiFieldErrors } from '../../lib/formErrors'
import { useChangePassword } from './useProfile'

type Values = {
  current_password: string
  password: string
  password_confirmation: string
}

export function PasswordForm() {
  const { t } = useI18n()
  const change = useChangePassword()
  const [formError, setFormError] = useState<string | null>(null)

  const schema = useMemo(
    () =>
      z
        .object({
          current_password: z.string().min(1, t('common.validation.required')),
          password: z
            .string()
            .min(8, t('common.validation.passwordMin', { min: 8 }))
            .regex(/[A-Za-z]/, t('common.validation.passwordLetter'))
            .regex(/[0-9]/, t('common.validation.passwordDigit')),
          password_confirmation: z.string(),
        })
        .refine((values) => values.password === values.password_confirmation, {
          path: ['password_confirmation'],
          message: t('common.validation.passwordMismatch'),
        }),
    [t],
  )

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      current_password: '',
      password: '',
      password_confirmation: '',
    },
  })

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null)

    try {
      await change.mutateAsync(values)
      form.reset({
        current_password: '',
        password: '',
        password_confirmation: '',
      })
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
        <h2 className="text-xl">{t('profile.passwordTitle')}</h2>
        <p className="text-sm text-ink-70">{t('profile.passwordText')}</p>
      </div>

      <form noValidate onSubmit={onSubmit} className="flex flex-col gap-4">
        {formError ? (
          <p role="alert" className="text-sm text-danger">
            {formError}
          </p>
        ) : null}

        <TextField
          label={t('profile.currentPassword')}
          type="password"
          autoComplete="current-password"
          error={form.formState.errors.current_password?.message}
          {...form.register('current_password')}
        />
        <TextField
          label={t('profile.newPassword')}
          type="password"
          autoComplete="new-password"
          hint={t('auth.passwordHint')}
          error={form.formState.errors.password?.message}
          {...form.register('password')}
        />
        <TextField
          label={t('auth.passwordConfirm')}
          type="password"
          autoComplete="new-password"
          error={form.formState.errors.password_confirmation?.message}
          {...form.register('password_confirmation')}
        />

        <div className="flex items-center gap-3">
          <Button type="submit" disabled={change.isPending}>
            {change.isPending ? t('profile.changingPassword') : t('profile.changePassword')}
          </Button>
          {change.isSuccess && !form.formState.isDirty ? (
            <span className="text-sm text-accent">{t('profile.passwordChanged')}</span>
          ) : null}
        </div>
      </form>
    </section>
  )
}
