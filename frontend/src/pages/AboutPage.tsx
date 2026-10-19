import { Link } from 'react-router'

import { Reveal } from '../components/layout/Reveal'
import { Section } from '../components/layout/Section'
import { useAuth } from '../features/auth/useAuth'
import { useI18n } from '../features/i18n/useI18n'
import { toDisplayDigits } from '../features/i18n/format'

import aboutImage from '../assets/rest.jpg'

export function AboutPage() {
  const { user } = useAuth()
  const { t, fmt } = useI18n()

  const principles = t('about.principles') as unknown as Array<{
    title: string
    text: string
  }>

  return (
    <article className="flex flex-col gap-0">
      <Section>
        <div className="grid items-center gap-12 lg:grid-cols-2">
          <Reveal className="flex max-w-2xl flex-col gap-5">
            <span className="text-sm font-medium text-accent">{t('about.kicker')}</span>
            <h1 className="text-balance">{t('about.title')}</h1>
            <p className="text-lg text-ink-70">{t('about.lead')}</p>
          </Reveal>
          <Reveal delay={0.15}>
            <img
              src={aboutImage}
              alt=""
              aria-hidden="true"
              className="h-80 w-full rounded-hero object-cover"
            />
          </Reveal>
        </div>
      </Section>

      <Section band>
        <Reveal className="flex flex-col gap-3">
          <h2 className="max-w-xl text-balance">{t('about.principlesTitle')}</h2>
          <p className="max-w-xl text-ink-70">{t('about.principlesLead')}</p>
        </Reveal>
        <ul className="mt-10 grid gap-5 sm:grid-cols-3">
          {principles.map((principle, index) => (
            <li key={principle.title}>
              <Reveal delay={0.1 * index} className="h-full">
                <div className="flex h-full flex-col gap-3 rounded-card border border-hairline bg-surface p-7">
                  <span aria-hidden="true" className="font-display text-2xl text-accent">
                    {toDisplayDigits(fmt.locale, String(index + 1))}
                  </span>
                  <h3 className="text-xl">{principle.title}</h3>
                  <p className="text-sm leading-7 text-ink-70">{principle.text}</p>
                </div>
              </Reveal>
            </li>
          ))}
        </ul>
      </Section>

      <Section>
        <Reveal className="flex flex-col items-start gap-4">
          <h2 className="text-balance">{t('about.ctaTitle')}</h2>
          <p className="max-w-xl text-ink-70">{t('about.ctaText')}</p>
          <div className="flex flex-wrap gap-3 pt-2">
            <Link
              to="/consultants"
              className="inline-flex h-11 items-center justify-center rounded-control bg-accent px-6 font-medium text-on-accent transition-colors hover:bg-accent-strong"
            >
              {t('nav.consultants')}
            </Link>
            {user ? null : (
              <Link
                to="/login"
                className="inline-flex h-11 items-center justify-center rounded-control border border-hairline bg-surface-strong px-6 text-ink transition-colors hover:bg-surface"
              >
                {t('nav.login')}
              </Link>
            )}
          </div>
        </Reveal>
      </Section>
    </article>
  )
}
