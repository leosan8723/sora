import type { ReactNode } from 'react'

type BadgeTone = 'neutral' | 'accent' | 'success' | 'muted'

interface BadgeProps {
  children: ReactNode
  tone?: BadgeTone
}

const TONES: Record<BadgeTone, string> = {
  neutral: 'bg-surface-strong text-ink-70',
  accent: 'bg-accent-wash text-accent',
  success: 'bg-success-wash text-success',
  muted: 'bg-surface text-ink-50',
}

export function Badge({ children, tone = 'neutral' }: BadgeProps) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-control px-2.5 py-1 text-xs leading-none ${TONES[tone]}`}
    >
      {children}
    </span>
  )
}
