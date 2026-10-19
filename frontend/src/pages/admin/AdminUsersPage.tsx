import { useState } from 'react'

import { Badge } from '../../components/ui/Badge'
import { Button } from '../../components/ui/Button'
import { Pagination } from '../../components/ui/Pagination'
import { Skeleton } from '../../components/ui/Skeleton'
import { StateMessage } from '../../components/ui/StateMessage'
import { TextField } from '../../components/ui/Field'
import { useAuth } from '../../features/auth/useAuth'
import {
  useAdminUsers,
  useAdminPasswordLink,
  useCreateAdminConsultant,
  useUpdateAdminUser,
} from '../../features/admin/useAdmin'
import type { AdminUser } from '../../features/admin/types'
import { useI18n } from '../../features/i18n/useI18n'
import { errorMessage } from '../../lib/api'

const ROLE_KEYS = ['client', 'consultant', 'admin'] as const

export function AdminUsersPage() {
  const { t } = useI18n()
  const [role, setRole] = useState('')
  const [query, setQuery] = useState('')
  const [page, setPage] = useState(1)

  const users = useAdminUsers({
    role: role === '' ? undefined : (role as AdminUser['role']),
    q: query || undefined,
    page,
  })
  const updateUser = useUpdateAdminUser()
  const createConsultant = useCreateAdminConsultant()
  const passwordLink = useAdminPasswordLink()
  const { user: actor } = useAuth()

  const [error, setError] = useState<string | null>(null)
  const [setupLink, setSetupLink] = useState<
    { url: string; expiresInMinutes: number } | null
  >(null)

  function toggleActive(target: AdminUser) {
    setError(null)
    updateUser.mutate(
      { id: target.id, payload: { is_active: !target.is_active } },
      { onError: (cause) => setError(errorMessage(cause, t('admin.errors.userUpdateFailed'))) },
    )
  }

  function makePasswordLink(target: AdminUser) {
    setError(null)
    setSetupLink(null)
    passwordLink.mutate(target.id, {
      onSuccess: (data) =>
        setSetupLink({
          url: data.setup_url,
          expiresInMinutes: data.expires_in_minutes,
        }),
      onError: (cause) =>
        setError(errorMessage(cause, t('admin.errors.passwordLinkFailed'))),
    })
  }

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-end gap-3">
        <div className="w-full max-w-xs">
          <TextField
            label={t('admin.searchByNameEmail')}
            value={query}
            onChange={(event) => {
              setQuery(event.target.value)
              setPage(1)
            }}
          />
        </div>
        <div className="flex gap-1 rounded-control border border-hairline p-1 text-sm">
          {['', ...ROLE_KEYS].map((value) => (
            <button
              key={value}
              type="button"
              aria-pressed={role === value}
              onClick={() => {
                setRole(value)
                setPage(1)
              }}
              className={`rounded-control px-3 py-1.5 transition-colors ${
                role === value ? 'bg-tint text-ink' : 'text-ink-70 hover:text-ink'
              }`}
            >
              {value === '' ? t('admin.roles.all') : t(`admin.roles.${value}`)}
            </button>
          ))}
        </div>
      </div>

      {error ? (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      ) : null}

      {setupLink ? (
        <div
          role="status"
          className="flex flex-col gap-2 rounded-card border border-hairline bg-surface-soft p-4"
        >
          <p className="text-sm text-ink">{t('admin.passwordLinkTitle')}</p>
          <p className="text-sm text-ink-70">{t('admin.passwordLinkOnce')}</p>
          <div className="flex flex-wrap items-center gap-2">
            <code
              dir="ltr"
              className="max-w-full overflow-x-auto rounded-panel bg-surface px-3 py-2 text-xs break-all text-ink-70"
            >
              {setupLink.url}
            </code>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => void navigator.clipboard?.writeText(setupLink.url)}
            >
              {t('admin.copyLink')}
            </Button>
          </div>
          <p className="text-xs text-ink-50">
            {t('admin.passwordLinkExpiry', { minutes: setupLink.expiresInMinutes })}
          </p>
          <div>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setSetupLink(null)}
            >
              {t('admin.passwordLinkDone')}
            </Button>
          </div>
        </div>
      ) : null}

      {users.isPending ? (
        <div className="flex flex-col gap-3" aria-hidden="true">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-16 w-full rounded-card" />
          ))}
        </div>
      ) : users.isError ? (
        <StateMessage tone="error" title={t('admin.errors.usersLoadFailed')} />
      ) : users.data.data.length === 0 ? (
        <StateMessage title={t('admin.noUsers')} description={t('admin.noUsersText')} />
      ) : (
        <ul className="flex flex-col gap-3">
          {users.data.data.map((user) => {
            const isSelf = user.id === actor?.id
            return (
              <li
                key={user.id}
                className="flex flex-col gap-3 rounded-card border border-hairline bg-surface p-5 sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="flex min-w-0 flex-col gap-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="text-ink">{user.name}</span>
                    <Badge tone={user.role === 'admin' ? 'accent' : 'neutral'}>
                      {t(`admin.roles.${user.role}`)}
                    </Badge>
                    {user.role === 'consultant' && user.consultant?.is_verified ? (
                      <Badge tone="success">{t('admin.verified')}</Badge>
                    ) : null}
                    {user.role === 'consultant' && user.password_set === false ? (
                      <Badge tone="accent">{t('admin.passwordPending')}</Badge>
                    ) : null}
                    {!user.is_active ? <Badge tone="muted">{t('admin.deactivated')}</Badge> : null}
                  </div>
                  <p className="truncate text-sm text-ink-50">
                    {user.email}
                    {user.phone ? ` — ${user.phone}` : ''}
                  </p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                  {isSelf ? (
                    <span className="text-xs text-ink-30">{t('admin.you')}</span>
                  ) : (
                    <>
                      {user.role === 'consultant' ? (
                        <Button
                          variant="secondary"
                          size="sm"
                          disabled={passwordLink.isPending}
                          onClick={() => makePasswordLink(user)}
                        >
                          {passwordLink.isPending
                            ? t('admin.passwordLinkPending')
                            : user.password_set === false
                              ? t('admin.createPasswordLink')
                              : t('admin.resetPasswordLink')}
                        </Button>
                      ) : null}
                      <Button
                        variant={user.is_active ? 'danger' : 'secondary'}
                        size="sm"
                        disabled={updateUser.isPending}
                        onClick={() => toggleActive(user)}
                      >
                        {user.is_active ? t('admin.deactivate') : t('admin.activate')}
                      </Button>
                    </>
                  )}
                </div>
              </li>
            )
          })}
        </ul>
      )}

      {users.data ? (
        <Pagination
          page={users.data.meta.current_page}
          lastPage={users.data.meta.last_page}
          total={users.data.meta.total}
          onPageChange={setPage}
        />
      ) : null}

      <NewConsultantForm
        pending={createConsultant.isPending}
        onSubmit={(payload) => {
          setError(null)
          createConsultant.mutate(payload, {
            onSuccess: () => setError(null),
            onError: (cause) =>
              setError(errorMessage(cause, t('admin.errors.consultantCreateFailed'))),
          })
        }}
      />
    </div>
  )
}

function NewConsultantForm({
  pending,
  onSubmit,
}: {
  pending: boolean
  onSubmit: (payload: { name: string; email: string; password?: string }) => void
}) {
  const { t } = useI18n()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault()
        onSubmit({ name, email, password: password === '' ? undefined : password })
        setName('')
        setEmail('')
        setPassword('')
      }}
      className="flex flex-col gap-3 rounded-card border border-dashed border-hairline bg-surface-soft p-6"
    >
      <h2 className="text-base">{t('admin.newConsultantTitle')}</h2>
      <div className="grid gap-3 sm:grid-cols-3">
        <TextField
          label={t('auth.name')}
          value={name}
          onChange={(event) => setName(event.target.value)}
          required
        />
        <TextField
          label={t('auth.email')}
          type="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          required
        />
        <TextField
          label={t('auth.password')}
          type="password"
          value={password}
          hint={t('auth.passwordHintOptional')}
          onChange={(event) => setPassword(event.target.value)}
        />
      </div>
      <div>
        <Button
          type="submit"
          disabled={pending || name === '' || email === ''}
        >
          {pending ? t('admin.creating') : t('admin.createConsultant')}
        </Button>
      </div>
    </form>
  )
}
