import { motion, useReducedMotion } from 'motion/react'
import type { ReactNode } from 'react'

interface RevealProps {
  /** Vertical offset in px the element animates from. */
  y?: number
  delay?: number
  className?: string
  children: ReactNode
}

/**
 * Scroll-entrance wrapper. A single, quiet reveal — respects the user's
 * reduced-motion preference by fading only (no movement).
 */
export function Reveal({ y = 24, delay = 0, className, children }: RevealProps) {
  const reducedMotion = useReducedMotion()

  return (
    <motion.div
      initial={{ opacity: 0, y: reducedMotion ? 0 : y }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-64px' }}
      transition={{ duration: 0.7, delay, ease: [0.22, 1, 0.36, 1] }}
      className={className}
    >
      {children}
    </motion.div>
  )
}
