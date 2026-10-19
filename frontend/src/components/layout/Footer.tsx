import { Link } from 'react-router'

import { useI18n } from '../../features/i18n/useI18n'
import { Container } from './Container'
import { Logo } from './Logo'

export function Footer() {
  const { t } = useI18n()

  const siteLinks = [
    { to: '/consultants', label: t('nav.consultants') },
    { to: '/about', label: t('nav.about') },
    { to: '/faq', label: t('nav.faq') },
    { to: '/contact', label: t('nav.contact') },
  ]

  const accountLinks = [
    { to: '/appointments', label: t('nav.appointments') },
    { to: '/dashboard', label: t('nav.dashboard') },
    { to: '/profile', label: t('nav.profile') },
  ]

  return (
    <footer className="border-t border-hairline-soft bg-deep">
      <Container className="flex flex-col gap-10 py-14">
        <div className="grid gap-10 sm:grid-cols-[1.4fr_1fr_1fr]">
          <div className="flex flex-col items-start gap-4">
            <Logo />
            <p className="max-w-sm text-sm leading-7 text-ink-50">{t('footer.about')}</p>
          </div>

          <nav aria-label={t('footer.links')} className="flex flex-col gap-3">
            <span className="text-sm text-ink">{t('footer.links')}</span>
            {siteLinks.map((link) => (
              <Link
                key={link.to}
                to={link.to}
                className="w-fit text-sm text-ink-50 transition-colors hover:text-ink"
              >
                {link.label}
              </Link>
            ))}
          </nav>

          <nav aria-label={t('nav.profile')} className="flex flex-col gap-3">
            <span className="text-sm text-ink">{t('footer.contactTitle')}</span>
            {accountLinks.map((link) => (
              <Link
                key={link.to}
                to={link.to}
                className="w-fit text-sm text-ink-50 transition-colors hover:text-ink"
              >
                {link.label}
              </Link>
            ))}
            <Link
              to="/contact"
              className="w-fit text-sm text-accent transition-colors hover:text-ink"
            >
              {t('footer.contactUs')}
            </Link>
          </nav>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-hairline-soft pt-6">
          <p className="text-sm text-ink-30">
            © {t('brand.name')} — {t('footer.rights')}
          </p>
          <p className="text-sm text-ink-30">{t('footer.madeFor')}</p>
        </div>
      </Container>
    </footer>
  )
}
