import { useState } from 'react'
import { Link } from 'react-router'

import { Badge } from '../components/ui/Badge'
import { Button } from '../components/ui/Button'
import { PasswordForm } from '../features/profile/PasswordForm'
import { ProfileForm } from '../features/profile/ProfileForm'
import { resendVerificationEmail } from '../features/auth/api'
import { useAuth } from '../features/auth/useAuth'
import { useI18n } from '../features/i18n/useI18n'
import { errorMessage } from '../lib/api'

function EmailVerificationPanel() {
  const { user, updateUser } = useAuth()
  const { t } = useI18n()
  const [pending, setPending] = useState(false)
  const [sent, setSent] = useState(false)
  const [error, setError] = useState<string | null>(null)

  if (!user) {
    return null
  }

  async function resend() {
    setPending(true)
    setSent(false)
    setError(null)
    try {
      const result = await resendVerificationEmail()
      updateUser({ email_verified: result.email_verified })
      setSent(true)
    } catch (cause) {
      setError(errorMessage(cause, t('verify.resendFailed')))
    } finally {
      setPending(false)
    }
  }

  if (user.email_verified) {
    return (
      <p className="flex items-center gap-2 text-sm text-ink-70">
        <Badge tone="success">{t('verify.verified')}</Badge>
        {t('verify.verifiedText')}
      </p>
    )
  }

  return (
    <div
      role="status"
      className="flex flex-wrap items-center gap-3 rounded-panel border border-accent/50 bg-accent-wash px-4 py-3 text-sm"
    >
      <Badge tone="accent">{t('verify.unverified')}</Badge>
      <span className="text-ink-70">{t('verify.unverifiedText')}</span>
      <Button size="sm" variant="secondary" disabled={pending} onClick={resend}>
        {pending ? t('verify.sending') : t('verify.resend')}
      </Button>
      {sent ? <span className="text-ink-70">{t('verify.sent')}</span> : null}
      {error ? (
        <span role="alert" className="text-danger">
          {error}
        </span>
      ) : null}
    </div>
  )
}

export function ProfilePage() {
  const { user } = useAuth()
  const { t } = useI18n()

  return (
    <div className="mx-auto flex w-full max-w-xl flex-col gap-6">
      <header className="flex flex-col gap-1">
        <h1 className="text-3xl">{t('profile.title')}</h1>
        <p className="text-ink-70">
          {user
            ? t('profile.currentlySignedIn', { email: user.email })
            : t('profile.signedOut')}
        </p>
      </header>

      <EmailVerificationPanel />
      <ProfileForm />
      <PasswordForm />

      <div className="flex justify-start">
        {user?.role === 'consultant' ? (
          <Link to="/dashboard" className="text-sm text-ink-70 hover:text-ink">
            {t('profile.toDashboard')}
          </Link>
        ) : (
          <Link to="/consultants" className="text-sm text-ink-70 hover:text-ink">
            {t('profile.toConsultants')}
          </Link>
        )}
      </div>
    </div>
  )
}
