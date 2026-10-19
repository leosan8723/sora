import { Link } from 'react-router'

import { Reveal } from '../components/layout/Reveal'
import { Section } from '../components/layout/Section'
import { useI18n } from '../features/i18n/useI18n'

export function FaqPage() {
  const { t } = useI18n()
  const items = t('faq.items') as unknown as Array<{ q: string; a: string }>

  return (
    <article className="flex flex-col gap-0">
      <Section>
        <Reveal className="flex max-w-2xl flex-col gap-5">
          <h1 className="text-balance">{t('faq.title')}</h1>
          <p className="text-lg text-ink-70">{t('faq.lead')}</p>
        </Reveal>

        <Reveal delay={0.1} className="mt-10">
          <ul className="flex flex-col overflow-hidden rounded-card border border-hairline bg-surface">
            {items.map((item) => (
              <li key={item.q} className="border-b border-hairline last:border-b-0">
                <details className="group px-6 py-5 [&_summary::-webkit-details-marker]:hidden">
                  <summary className="flex cursor-pointer items-center justify-between gap-4 text-base text-ink transition-colors hover:text-accent">
                    {item.q}
                    <span
                      aria-hidden="true"
                      className="shrink-0 text-accent transition-transform duration-200 group-open:rotate-45"
                    >
                      +
                    </span>
                  </summary>
                  <p className="mt-3 max-w-3xl text-sm leading-7 text-ink-70">
                    {item.a}
                  </p>
                </details>
              </li>
            ))}
          </ul>
        </Reveal>
      </Section>

      <Section band>
        <Reveal className="flex flex-col items-start gap-4">
          <h2 className="text-balance">{t('faq.ctaTitle')}</h2>
          <p className="max-w-xl text-ink-70">{t('faq.ctaText')}</p>
          <Link
            to="/contact"
            className="inline-flex h-11 items-center justify-center rounded-control bg-accent px-6 font-medium text-on-accent transition-colors hover:bg-accent-strong"
          >
            {t('faq.ctaButton')}
          </Link>
        </Reveal>
      </Section>
    </article>
  )
}
