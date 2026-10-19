import type { HTMLAttributes } from 'react'

interface ContainerProps extends HTMLAttributes<HTMLDivElement> {
  size?: 'narrow' | 'page'
}

export function Container({
  size = 'page',
  className = '',
  ...rest
}: ContainerProps) {
  const width = size === 'narrow' ? 'max-w-xl' : 'max-w-(--container-page)'

  return (
    <div
      className={`mx-auto w-full ${width} px-6 sm:px-8 ${className}`}
      {...rest}
    />
  )
}
