import type { HTMLAttributes } from 'react'

interface SkeletonProps extends HTMLAttributes<HTMLDivElement> {}

export function Skeleton({ className = '', ...rest }: SkeletonProps) {
  return (
    <div
      role="status"
      className={`relative overflow-hidden rounded-control bg-surface-soft after:absolute after:inset-0 after:-translate-x-full after:animate-[shimmer_1.6s_infinite] after:bg-[linear-gradient(90deg,transparent,var(--t-shimmer),transparent)] ${className}`}
      {...rest}
    />
  )
}
