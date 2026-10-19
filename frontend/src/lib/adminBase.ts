// The private admin panel base path is injected server-side by the admin
// shell document (see backend AdminPanelController). It is never present in
// the production bundle: the dev-only fallback below is dead-code
// eliminated by Vite (import.meta.env.DEV) when building for production.
function readAdminBase(): string | null {
  if (typeof window === 'undefined') {
    return null
  }
  const injected = window.__ADMIN_BASE__
  if (typeof injected === 'string' && injected.startsWith('/')) {
    return injected.replace(/\/+$/, '')
  }
  if (import.meta.env.DEV) {
    return '/admin'
  }
  return null
}

/** Private admin base path, or null when the shell is not available. */
export const ADMIN_BASE: string | null = readAdminBase()

/**
 * Builds an admin navigation href; only valid while ADMIN_BASE is non-null
 * (the admin route tree is not registered otherwise).
 */
export function adminHref(suffix = ''): string {
  return `${ADMIN_BASE ?? ''}${suffix}`
}
