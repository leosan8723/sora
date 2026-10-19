import type { HTMLAttributes } from 'react'

import { Container } from './Container'

interface SectionProps extends HTMLAttributes<HTMLElement> {
  /** Renders the section on the deeper band background with hairline edges. */
  band?: boolean
  size?: 'narrow' | 'page'
}

export function Section({
  band = false,
  size = 'page',
  className = '',
  children,
  ...rest
}: SectionProps) {
  const classNames = [
    band ? 'border-y border-hairline-soft bg-deep' : '',
    className,
  ]
    .filter(Boolean)
    .join(' ')

  return (
    <section className={classNames} {...rest}>
      <Container size={size} className="py-16 sm:py-24">
        {children}
      </Container>
    </section>
  )
}
