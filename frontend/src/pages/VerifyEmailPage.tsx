import { useEffect, useRef, useState } from 'react'
import { Link, useSearchParams } from 'react-router'

import { Container } from '../components/layout/Container'
import { StateMessage } from '../components/ui/StateMessage'
import { verifyEmailRequest } from '../features/auth/api'
import { useI18n } from '../features/i18n/useI18n'
import { errorMessage } from '../lib/api'

type Phase = 'pending' | 'verified' | 'failed'

/**
 * Landing page for the emailed verification link. Reads id/token/expires
 * from the query, forwards them once to the public API endpoint and shows
 * the localized outcome. A missing or malformed link is an immediate fail —
 * the token is single-use by design.
 */
export function VerifyEmailPage() {
  const { t } = useI18n()
  const [params] = useSearchParams()
  const [phase, setPhase] = useState<Phase>('pending')
  const [error, setError] = useState<string | null>(null)
  const started = useRef(false)

  useEffect(() => {
    if (started.current) {
      return
    }

    const id = params.get('id')
    const token = params.get('token')
    const expires = params.get('expires')

    if (!id || !token || !expires) {
      setPhase('failed')
      setError(t('verify.invalidLink'))
      return
    }

    started.current = true
    verifyEmailRequest({ id, token, expires })
      .then(() => setPhase('verified'))
      .catch((cause) => {
        setError(errorMessage(cause, t('verify.invalidLink')))
        setPhase('failed')
      })
  }, [params, t])

  return (
    <Container className="flex min-h-[50vh] max-w-md flex-col items-center justify-center gap-6 py-16">
      <h1 className="text-2xl">{t('verify.pageTitle')}</h1>
      {phase === 'pending' ? (
        <StateMessage title={t('verify.pending')} />
      ) : phase === 'verified' ? (
        <StateMessage
          tone="success"
          title={t('verify.okTitle')}
          description={t('verify.okText')}
          action={
            <Link
              to="/"
              className="inline-flex h-10 items-center rounded-control border border-hairline bg-surface-strong px-5 text-sm text-ink transition-colors hover:bg-surface"
            >
              {t('verify.backHome')}
            </Link>
          }
        />
      ) : (
        <StateMessage
          tone="error"
          title={t('verify.failedTitle')}
          description={error ?? undefined}
        />
      )}
    </Container>
  )
}
