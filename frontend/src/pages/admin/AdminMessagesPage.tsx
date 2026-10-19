import { useState } from 'react'

import { Badge } from '../../components/ui/Badge'
import { Button } from '../../components/ui/Button'
import { Pagination } from '../../components/ui/Pagination'
import { Skeleton } from '../../components/ui/Skeleton'
import { StateMessage } from '../../components/ui/StateMessage'
import { useAdminMessages, useUpdateAdminMessage } from '../../features/admin/useAdmin'
import { useI18n } from '../../features/i18n/useI18n'
import { errorMessage } from '../../lib/api'

type Tab = '' | 'new' | 'handled'

const STATUS_TABS: Tab[] = ['', 'new', 'handled']

export function AdminMessagesPage() {
  const { t, fmt } = useI18n()
  const [status, setStatus] = useState<Tab>('')
  const [page, setPage] = useState(1)
  const messages = useAdminMessages({ status: status || undefined, page })
  const update = useUpdateAdminMessage()
  const [error, setError] = useState<string | null>(null)

  return (
    <div className="flex flex-col gap-5">
      <div className="flex gap-1 w-fit rounded-control border border-hairline p-1 text-sm">
        {STATUS_TABS.map((tab) => (
          <button
            key={tab}
            type="button"
            aria-pressed={status === tab}
            onClick={() => {
              setStatus(tab)
              setPage(1)
            }}
            className={`rounded-control px-3 py-1.5 transition-colors ${
              status === tab ? 'bg-tint text-ink' : 'text-ink-70 hover:text-ink'
            }`}
          >
            {tab === '' ? t('admin.roles.all') : t(`admin.messages.${tab}`)}
          </button>
        ))}
      </div>

      {error ? (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      ) : null}

      {messages.isPending ? (
        <div className="flex flex-col gap-3" aria-hidden="true">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-24 w-full rounded-card" />
          ))}
        </div>
      ) : messages.isError ? (
        <StateMessage tone="error" title={t('admin.errors.messagesLoadFailed')} />
      ) : messages.data.data.length === 0 ? (
        <StateMessage
          title={t('admin.messages.noneTitle')}
          description={t('admin.messages.noneText')}
        />
      ) : (
        <ul className="flex flex-col gap-3">
          {messages.data.data.map((message) => (
            <li key={message.id} className="rounded-card border border-hairline bg-surface p-5">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="text-ink">{message.subject}</span>
                  <Badge tone={message.status === 'new' ? 'accent' : 'neutral'}>
                    {message.status === 'new' ? t('admin.messages.new') : t('admin.messages.handled')}
                  </Badge>
                </div>
                {message.created_at ? (
                  <span className="text-xs text-ink-30">{fmt.date(message.created_at)}</span>
                ) : null}
              </div>
              <p className="mt-2 text-sm text-ink-70">{message.body}</p>
              <p className="mt-2 text-sm text-ink-50">
                {message.name} — {message.email}
                {message.user_id
                  ? ` — ${t('admin.messages.registeredUser', { id: fmt.number(message.user_id) })}`
                  : ''}
              </p>
              <div className="mt-3">
                <Button
                  variant={message.status === 'new' ? 'secondary' : 'ghost'}
                  size="sm"
                  disabled={update.isPending}
                  onClick={() => {
                    setError(null)
                    update.mutate(
                      { id: message.id, status: message.status === 'new' ? 'handled' : 'new' },
                      {
                        onError: (cause) =>
                          setError(errorMessage(cause, t('admin.errors.messageUpdateFailed'))),
                      },
                    )
                  }}
                >
                  {message.status === 'new'
                    ? t('admin.messages.markHandled')
                    : t('admin.messages.markNew')}
                </Button>
              </div>
            </li>
          ))}
        </ul>
      )}

      {messages.data ? (
        <Pagination
          page={messages.data.meta.current_page}
          lastPage={messages.data.meta.last_page}
          total={messages.data.meta.total}
          onPageChange={setPage}
        />
      ) : null}
    </div>
  )
}
