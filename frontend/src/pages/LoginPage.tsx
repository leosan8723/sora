import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, Navigate, useLocation, useNavigate } from 'react-router'
import { z } from 'zod'

import { Button } from '../components/ui/Button'
import { TextField } from '../components/ui/Field'
import { useAuth } from '../features/auth/useAuth'
import { useI18n } from '../features/i18n/useI18n'
import { applyApiFieldErrors } from '../lib/formErrors'

type LoginValues = z.infer<ReturnType<typeof buildLoginSchema>>
type RegisterValues = z.infer<ReturnType<typeof buildRegisterSchema>>

type Mode = 'login' | 'register'

export function LoginPage() {
  const { user, status, login, register: registerAccount } = useAuth()
  const { t } = useI18n()
  const navigate = useNavigate()
  const location = useLocation()
  const [mode, setMode] = useState<Mode>('login')

  const from = (location.state as { from?: string } | null)?.from

  const loginForm = useForm<LoginValues>({
    resolver: zodResolver(
      useMemo(
        () =>
          buildLoginSchema((path, vars) => t(path, vars)),
        [t],
      ),
    ),
    defaultValues: { email: '', password: '' },
  })

  const registerForm = useForm<RegisterValues>({
    resolver: zodResolver(
      useMemo(
        () =>
          buildRegisterSchema((path, vars) => t(path, vars)),
        [t],
      ),
    ),
    defaultValues: {
      name: '',
      email: '',
      phone: '',
      password: '',
      password_confirmation: '',
    },
  })

  const [loginFormError, setLoginFormError] = useState<string | null>(null)
  const [registerFormError, setRegisterFormError] = useState<string | null>(null)

  const onLogin = loginForm.handleSubmit(async (values) => {
    setLoginFormError(null)
    try {
      const account = await login(values)
      navigate(
        from ?? (account.role === 'consultant' ? '/dashboard' : '/consultants'),
        { replace: true },
      )
    } catch (error) {
      applyApiFieldErrors(
        error,
        (name, message) =>
          loginForm.setError(name as keyof LoginValues, { message }),
        setLoginFormError,
        t('common.unexpectedError'),
      )
    }
  })

  const onRegister = registerForm.handleSubmit(async (values) => {
    setRegisterFormError(null)
    try {
      const account = await registerAccount({
        name: values.name,
        email: values.email,
        phone: values.phone ? values.phone : null,
        password: values.password,
        password_confirmation: values.password_confirmation,
      })
      navigate(
        account.role === 'consultant' ? '/dashboard' : '/consultants',
        { replace: true },
      )
    } catch (error) {
      applyApiFieldErrors(
        error,
        (name, message) =>
          registerForm.setError(name as keyof RegisterValues, { message }),
        setRegisterFormError,
        t('common.unexpectedError'),
      )
    }
  })

  if (status === 'authenticated' && user) {
    const target =
      from ?? (user.role === 'consultant' ? '/dashboard' : '/consultants')
    return <Navigate to={target} replace />
  }

  return (
    <div className="mx-auto flex w-full max-w-md flex-col justify-center gap-6 py-8">
      <Link to="/" className="text-sm text-ink-50 transition-colors hover:text-ink">
        {t('auth.backHome')}
      </Link>

      <div className="flex flex-col gap-1">
        <h1 className="text-2xl">
          {mode === 'login' ? t('auth.loginTitle') : t('auth.registerTitle')}
        </h1>
        <p className="text-sm text-ink-70">
          {mode === 'login' ? t('auth.loginSubtitle') : t('auth.registerSubtitle')}
        </p>
      </div>

      <div
        role="tablist"
        aria-label={t('auth.modeLabel')}
        className="flex rounded-control border border-hairline bg-surface-soft p-1"
      >
        <button
          type="button"
          role="tab"
          aria-selected={mode === 'login'}
          onClick={() => setMode('login')}
          className={`flex-1 rounded-control px-3 py-2 text-sm transition-colors ${
            mode === 'login' ? 'bg-surface-strong text-ink' : 'text-ink-70'
          }`}
        >
          {t('nav.login')}
        </button>
        <button
          type="button"
          role="tab"
          aria-selected={mode === 'register'}
          onClick={() => setMode('register')}
          className={`flex-1 rounded-control px-3 py-2 text-sm transition-colors ${
            mode === 'register' ? 'bg-surface-strong text-ink' : 'text-ink-70'
          }`}
        >
          {t('nav.register')}
        </button>
      </div>

      {mode === 'login' ? (
        <form
          noValidate
          onSubmit={onLogin}
          className="flex flex-col gap-4 rounded-card border border-hairline bg-surface p-6"
        >
          {loginFormError ? (
            <p role="alert" className="text-sm text-danger">
              {loginFormError}
            </p>
          ) : null}
          <TextField
            label={t('auth.email')}
            type="email"
            autoComplete="email"
            error={loginForm.formState.errors.email?.message}
            {...loginForm.register('email')}
          />
          <TextField
            label={t('auth.password')}
            type="password"
            autoComplete="current-password"
            error={loginForm.formState.errors.password?.message}
            {...loginForm.register('password')}
          />
          <Button type="submit" disabled={loginForm.formState.isSubmitting}>
            {loginForm.formState.isSubmitting ? t('auth.submittingLogin') : t('nav.login')}
          </Button>
        </form>
      ) : (
        <form
          noValidate
          onSubmit={onRegister}
          className="flex flex-col gap-4 rounded-card border border-hairline bg-surface p-6"
        >
          {registerFormError ? (
            <p role="alert" className="text-sm text-danger">
              {registerFormError}
            </p>
          ) : null}
          <TextField
            label={t('auth.name')}
            autoComplete="name"
            error={registerForm.formState.errors.name?.message}
            {...registerForm.register('name')}
          />
          <TextField
            label={t('auth.email')}
            type="email"
            autoComplete="email"
            error={registerForm.formState.errors.email?.message}
            {...registerForm.register('email')}
          />
          <TextField
            label={t('auth.phoneOptional')}
            inputMode="tel"
            autoComplete="tel"
            error={registerForm.formState.errors.phone?.message}
            {...registerForm.register('phone')}
          />
          <TextField
            label={t('auth.password')}
            type="password"
            autoComplete="new-password"
            hint={t('auth.passwordHint')}
            error={registerForm.formState.errors.password?.message}
            {...registerForm.register('password')}
          />
          <TextField
            label={t('auth.passwordConfirm')}
            type="password"
            autoComplete="new-password"
            error={registerForm.formState.errors.password_confirmation?.message}
            {...registerForm.register('password_confirmation')}
          />
          <Button type="submit" disabled={registerForm.formState.isSubmitting}>
            {registerForm.formState.isSubmitting
              ? t('auth.submittingRegister')
              : t('auth.createAccount')}
          </Button>
        </form>
      )}

      {import.meta.env.DEV ? (
        <p className="text-xs text-ink-50">
          {t('auth.demoConsultantHint')}: consultant1@example.com / password
        </p>
      ) : null}
    </div>
  )
}

function buildLoginSchema(t: (path: string, vars?: Record<string, string | number>) => string) {
  return z.object({
    email: z.email(t('common.validation.email')),
    password: z.string().min(1, t('common.validation.required')),
  })
}

function buildRegisterSchema(t: (path: string, vars?: Record<string, string | number>) => string) {
  return z
    .object({
      name: z.string().min(2, t('common.validation.nameMin')).max(120),
      email: z.email(t('common.validation.email')),
      phone: z
        .string()
        .max(20, t('common.validation.phoneMax'))
        .optional()
        .or(z.literal('')),
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
    })
}
