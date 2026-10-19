import type { ReactNode } from 'react'

interface StateMessageProps {
  title: string
  description?: string
  action?: ReactNode
  tone?: 'neutral' | 'error' | 'success'
}

export function StateMessage({
  title,
  description,
  action,
  tone = 'neutral',
}: StateMessageProps) {
  return (
    <div
      role={tone === 'error' ? 'alert' : undefined}
      className={`flex flex-col items-start gap-3 rounded-card border p-7 ${
        tone === 'error'
          ? 'border-danger/40 bg-danger-wash'
          : tone === 'success'
            ? 'border-accent/30 bg-accent/5'
            : 'border-dashed border-hairline bg-surface-soft'
      }`}
    >
      <div className="flex flex-col gap-1.5">
        <p className={`text-base ${tone === 'error' ? 'text-danger' : 'text-ink-80'}`}>{title}</p>
        {description ? (
          <p className="text-sm leading-7 text-ink-50">{description}</p>
        ) : null}
      </div>
      {action}
    </div>
  )
}
