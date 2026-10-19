import { NavLink, Outlet } from 'react-router'

import { Container } from '../../components/layout/Container'
import { useAuth } from '../../features/auth/useAuth'
import { useI18n } from '../../features/i18n/useI18n'
import { adminHref } from '../../lib/adminBase'

export function AdminLayoutPage() {
  const { user } = useAuth()
  const { t } = useI18n()

  const navItems = [
    { to: adminHref(), label: t('admin.nav.summary'), end: true },
    { to: adminHref('/consultants'), label: t('nav.consultants'), end: false },
    { to: adminHref('/users'), label: t('admin.nav.users'), end: false },
    { to: adminHref('/appointments'), label: t('admin.nav.appointments'), end: false },
    { to: adminHref('/pricing'), label: t('admin.nav.pricing'), end: false },
    { to: adminHref('/reviews'), label: t('admin.nav.reviews'), end: false },
    { to: adminHref('/reports'), label: t('admin.nav.reports'), end: false },
    { to: adminHref('/messages'), label: t('admin.nav.messages'), end: false },
    { to: adminHref('/settings'), label: t('admin.nav.settings'), end: false },
  ]

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-col gap-2">
        <h1 className="text-2xl">{t('admin.title')}</h1>
        {user ? (
          <p className="text-sm text-ink-50">{t('admin.signedInAs', { name: user.name })}</p>
        ) : null}
      </header>

      <nav aria-label={t('admin.navAria')} className="overflow-x-auto">
        <ul className="flex w-fit min-w-max gap-1 rounded-card border border-hairline bg-surface p-1">
          {navItems.map((item) => (
            <li key={item.to}>
              <NavLink
                to={item.to}
                end={item.end}
                className={({ isActive }) =>
                  `block whitespace-nowrap rounded-panel px-3.5 py-2 text-sm transition-colors ${
                    isActive
                      ? 'bg-tint text-ink'
                      : 'text-ink-70 hover:bg-surface hover:text-ink'
                  }`
                }
              >
                {item.label}
              </NavLink>
            </li>
          ))}
        </ul>
      </nav>

      <Container className="px-0 sm:px-0">
        <Outlet />
      </Container>
    </div>
  )
}
