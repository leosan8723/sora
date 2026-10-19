import { useReducedMotion, AnimatePresence, motion } from 'motion/react'
import { useEffect, useState } from 'react'
import { NavLink, useNavigate } from 'react-router'

import { useAuth } from '../../features/auth/useAuth'
import { useI18n } from '../../features/i18n/useI18n'
import type { Locale } from '../../features/i18n/types'
import { useTheme } from '../../features/theme/useTheme'
import type { Theme } from '../../features/theme/theme'
import { ADMIN_BASE, adminHref } from '../../lib/adminBase'
import { Button } from '../ui/Button'
import { Container } from './Container'
import { Logo } from './Logo'

function ThemeToggle({ mobile = false }: { mobile?: boolean }) {
  const { theme, toggle } = useTheme()
  const { t } = useI18n()
  const next: Theme = theme === 'dark' ? 'light' : 'dark'

  return (
    <button
      type="button"
      onClick={toggle}
      title={t(`theme.${next}`)}
      aria-label={t(`theme.${next}`)}
      className={`flex size-10 items-center justify-center rounded-control text-ink-70 transition-colors hover:bg-surface hover:text-ink ${
        mobile ? 'sm:hidden' : 'hidden sm:flex'
      }`}
    >
      <ThemeGlyph theme={next} />
    </button>
  )
}

function ThemeGlyph({ theme }: { theme: Theme }) {
  // Show the theme the user would switch *to*, matching common toggle UX.
  if (theme === 'light') {
    return (
      <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true">
        <circle cx="10" cy="10" r="4" fill="currentColor" />
        <path
          d="M10 2v2M10 16v2M18 10h-2M4 10H2M15.5 4.5l-1.4 1.4M5.9 14.1l-1.4 1.4M15.5 15.5l-1.4-1.4M5.9 5.9L4.5 4.5"
          stroke="currentColor"
          strokeWidth="1.5"
          strokeLinecap="round"
        />
      </svg>
    )
  }

  return (
    <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true">
      <path d="M16 11.5A6.5 6.5 0 0 1 8.5 4a6.5 6.5 0 1 0 7.5 7.5Z" fill="currentColor" />
    </svg>
  )
}

function LocaleToggle({ mobile = false }: { mobile?: boolean }) {
  const { locale, setLocale } = useI18n()
  const { t } = useI18n()
  const next: Locale = locale === 'fa' ? 'en' : 'fa'

  return (
    <button
      type="button"
      onClick={() => setLocale(next)}
      title={t('locale.switch')}
      aria-label={t('locale.switch')}
      className={`flex size-10 items-center justify-center rounded-control text-[13px] font-medium text-ink-70 transition-colors hover:bg-surface hover:text-ink ${
        mobile ? 'sm:hidden' : 'hidden sm:flex'
      }`}
    >
      {locale === 'fa' ? 'EN' : 'فا'}
    </button>
  )
}

function navClasses({ isActive }: { isActive: boolean }): string {
  return `rounded-control px-3 py-1.5 text-sm transition-colors ${
    isActive ? 'bg-tint text-ink' : 'text-ink-70 hover:bg-surface hover:text-ink'
  }`
}

export function Header() {
  const { user, status, logout } = useAuth()
  const { t } = useI18n()
  const navigate = useNavigate()
  const reducedMotion = useReducedMotion()
  const [menuOpen, setMenuOpen] = useState(false)

  useEffect(() => {
    if (!menuOpen) {
      return
    }
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setMenuOpen(false)
      }
    }
    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [menuOpen])

  useEffect(() => {
    document.body.style.overflow = menuOpen ? 'hidden' : ''
    return () => {
      document.body.style.overflow = ''
    }
  }, [menuOpen])

  async function handleLogout() {
    setMenuOpen(false)
    await logout()
    navigate('/')
  }

  const links = [
    { to: '/consultants', label: t('nav.consultants') },
    { to: '/about', label: t('nav.about') },
    { to: '/faq', label: t('nav.faq') },
    ...(user?.role === 'client' ? [{ to: '/appointments', label: t('nav.appointments') }] : []),
    ...(user?.role === 'consultant' ? [{ to: '/dashboard', label: t('nav.dashboard') }] : []),
    ...(user?.role === 'admin' && ADMIN_BASE !== null
      ? [{ to: adminHref(), label: t('nav.admin') }]
      : []),
  ]

  return (
    <header className="glass sticky top-0 z-20 border-b border-hairline-soft">
      <Container className="flex h-16 items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Logo />
        </div>

        <div className="flex h-16 items-center gap-2">
          <nav aria-label={t('nav.openMenu')} className="hidden items-center gap-1 sm:flex">
            {links.map((link) => (
              <NavLink key={link.to} to={link.to} className={navClasses}>
                {link.label}
              </NavLink>
            ))}
          </nav>

          <div className="hidden items-center gap-2 sm:flex">
            {status === 'authenticated' && user ? (
              <>
                <span className="text-sm text-ink-50">{user.name}</span>
                <Button variant="ghost" size="sm" onClick={handleLogout}>
                  {t('nav.logout')}
                </Button>
              </>
            ) : status === 'guest' ? (
              <Button size="sm" onClick={() => navigate('/login')}>
                {t('nav.login')}
              </Button>
            ) : null}
            <LocaleToggle />
            <ThemeToggle />
          </div>

          <LocaleToggle mobile />
          <ThemeToggle mobile />

          <button
            type="button"
            aria-expanded={menuOpen}
            aria-controls="mobile-menu"
            aria-label={menuOpen ? t('nav.closeMenu') : t('nav.openMenu')}
            onClick={() => setMenuOpen((open) => !open)}
            className="flex size-10 items-center justify-center rounded-control text-ink transition-colors hover:bg-surface sm:hidden"
          >
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
              {menuOpen ? (
                <path
                  d="M5 5l10 10M15 5L5 15"
                  stroke="currentColor"
                  strokeWidth="1.5"
                  strokeLinecap="round"
                />
              ) : (
                <path
                  d="M3 6h14M3 10h14M3 14h14"
                  stroke="currentColor"
                  strokeWidth="1.5"
                  strokeLinecap="round"
                />
              )}
            </svg>
          </button>
        </div>
      </Container>

      <AnimatePresence>
        {menuOpen ? (
          <motion.div
            id="mobile-menu"
            initial={{ opacity: 0, y: reducedMotion ? 0 : -8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: reducedMotion ? 0 : -8 }}
            transition={{ duration: 0.2, ease: 'easeOut' }}
            className="fixed inset-x-0 top-16 z-10 border-b border-hairline bg-canvas/95 backdrop-blur sm:hidden"
          >
            <Container className="flex flex-col gap-1 py-4">
              {links.map((link) => (
                <NavLink
                  key={link.to}
                  to={link.to}
                  onClick={() => setMenuOpen(false)}
                  className="rounded-control px-3 py-3 text-base text-ink-80 transition-colors hover:bg-surface hover:text-ink"
                >
                  {link.label}
                </NavLink>
              ))}

              <div className="mt-3 border-t border-hairline-soft pt-3">
                {status === 'authenticated' && user ? (
                  <>
                    <NavLink
                      to="/profile"
                      onClick={() => setMenuOpen(false)}
                      className="rounded-control px-3 py-3 text-base text-ink-80 transition-colors hover:bg-surface hover:text-ink"
                    >
                      {t('nav.profile')} — {user.name}
                    </NavLink>
                    <button
                      type="button"
                      onClick={handleLogout}
                      className="w-full rounded-control px-3 py-3 text-start text-base text-ink-70 transition-colors hover:bg-surface hover:text-ink"
                    >
                      {t('nav.logout')}
                    </button>
                  </>
                ) : status === 'guest' ? (
                  <Button
                    className="w-full"
                    onClick={() => {
                      setMenuOpen(false)
                      navigate('/login')
                    }}
                  >
                    {t('nav.login')}
                  </Button>
                ) : null}
              </div>
            </Container>
          </motion.div>
        ) : null}
      </AnimatePresence>
    </header>
  )
}
