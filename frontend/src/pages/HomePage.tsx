import { motion, useReducedMotion } from 'motion/react'
import { Link } from 'react-router'

import { Container } from '../components/layout/Container'
import { Reveal } from '../components/layout/Reveal'
import { Section } from '../components/layout/Section'
import { ConsultantCard } from '../features/consultants/ConsultantCard'
import { useConsultants } from '../features/consultants/useConsultants'
import { useI18n } from '../features/i18n/useI18n'

import aboutImage from '../assets/calm.jpg'
import heroImage from '../assets/hero.jpg'

export function HomePage() {
  const reducedMotion = useReducedMotion()
  const { t, fmt } = useI18n()
  const consultantsQuery = useConsultants({ page: 1, perPage: 3 })
  const featured = consultantsQuery.data?.data.slice(0, 3) ?? []

  const services = t('home.serviceCards') as unknown as Array<{ title: string; text: string }>
  const steps = t('home.steps') as unknown as Array<{ title: string; text: string }>
  const faqItems = t('faq.items') as unknown as Array<{ q: string; a: string }>
  const introCards = [
    { title: t('home.missionTitle'), text: t('home.missionText') },
    { title: t('home.visionTitle'), text: t('home.visionText') },
    { title: t('home.valuesTitle'), text: t('home.valuesText') },
  ]

  return (
    <div className="flex flex-col gap-0">
      {/* Hero — text beside the calm photo, in the reference's spirit */}
      <section aria-label={t('home.heroAria')} className="-mt-12 sm:-mt-16">
        <Container className="grid items-center gap-10 pt-12 pb-16 sm:pt-16 sm:pb-24 lg:grid-cols-2">
          <motion.div
            initial={{ opacity: 0, y: reducedMotion ? 0 : 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.7, delay: reducedMotion ? 0 : 0.15, ease: [0.22, 1, 0.36, 1] }}
            className="flex flex-col items-start gap-6"
          >
            <h1 className="max-w-xl text-balance">{t('home.heroTitle')}</h1>
            <p className="max-w-lg text-lg text-ink-70">{t('home.heroSubtitle')}</p>
            <div className="flex flex-wrap gap-3 pt-1">
              <Link
                to="/consultants"
                className="inline-flex h-12 items-center justify-center rounded-control bg-accent px-7 font-medium text-on-accent shadow-card transition-colors hover:bg-accent-strong"
              >
                {t('home.heroPrimary')}
              </Link>
              <Link
                to="/about"
                className="inline-flex h-12 items-center justify-center rounded-control border border-hairline bg-surface-strong px-7 text-ink transition-colors hover:bg-surface"
              >
                {t('home.heroSecondary')}
              </Link>
            </div>
          </motion.div>

          <Reveal delay={0.2}>
            <img
              src={heroImage}
              alt=""
              aria-hidden="true"
              className="h-80 w-full rounded-hero object-cover sm:h-[26rem]"
            />
          </Reveal>
        </Container>
      </section>

      {/* About — cream band with mission / vision / values */}
      <Section band>
        <div className="grid items-center gap-12 lg:grid-cols-2">
          <Reveal className="flex flex-col gap-5">
            <span className="text-sm font-medium text-accent">{t('home.introKicker')}</span>
            <h2 className="max-w-md text-balance">{t('home.introTitle')}</h2>
            <p className="max-w-lg text-ink-70">{t('home.introText')}</p>
            <div className="grid gap-4 pt-2 sm:grid-cols-3">
              {introCards.map((card) => (
                <div key={card.title} className="flex flex-col gap-1.5">
                  <h3 className="text-base">{card.title}</h3>
                  <p className="text-sm leading-7 text-ink-50">{card.text}</p>
                </div>
              ))}
            </div>
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

      {/* Services — birthdate light-lime cards, as in the reference */}
      <Section>
        <Reveal className="flex flex-col gap-4">
          <span className="text-sm font-medium text-accent">{t('home.servicesKicker')}</span>
          <h2 className="max-w-xl text-balance">{t('home.servicesTitle')}</h2>
          <p className="max-w-xl text-ink-70">{t('home.servicesText')}</p>
        </Reveal>
        <div className="mt-10 grid gap-5 sm:grid-cols-3">
          {services.map((service, index) => (
            <Reveal key={service.title} delay={0.08 * index} className="h-full">
              <div className="flex h-full flex-col gap-3 rounded-card bg-tint p-7">
                <span
                  aria-hidden="true"
                  className="grid size-10 place-items-center rounded-panel bg-canvas/70 text-sm font-semibold text-accent-strong"
                >
                  {fmt.digits(String(index + 1))}
                </span>
                <h3 className="text-xl">{service.title}</h3>
                <p className="text-sm leading-7 text-ink-70">{service.text}</p>
              </div>
            </Reveal>
          ))}
        </div>
      </Section>

      {/* Process — numbered steps on white */}
      <Section band>
        <Reveal className="flex flex-col gap-4">
          <span className="text-sm font-medium text-accent">{t('home.processKicker')}</span>
          <h2 className="max-w-xl text-balance">{t('home.processTitle')}</h2>
          <p className="max-w-xl text-ink-70">{t('home.processText')}</p>
        </Reveal>
        <ol className="mt-10 grid gap-5 sm:grid-cols-3">
          {steps.map((step, index) => (
            <li key={step.title}>
              <Reveal delay={0.08 * index} className="h-full">
                <div className="flex h-full flex-col gap-3 rounded-card border border-hairline bg-surface p-7">
                  <span
                    aria-hidden="true"
                    className="font-display text-4xl font-semibold text-accent"
                  >
                    {fmt.digits(String(index + 1))}
                  </span>
                  <h3 className="text-xl">{step.title}</h3>
                  <p className="text-sm leading-7 text-ink-70">{step.text}</p>
                </div>
              </Reveal>
            </li>
          ))}
        </ol>
      </Section>

      {/* Real consultants preview */}
      <Section>
        <Reveal className="flex flex-col gap-4">
          <span className="text-sm font-medium text-accent">{t('home.consultantsKicker')}</span>
          <h2 className="max-w-xl text-balance">{t('home.consultantsTitle')}</h2>
          <p className="max-w-xl text-ink-70">{t('home.consultantsText')}</p>
        </Reveal>

        {consultantsQuery.isLoading ? (
          <div className="mt-10 grid gap-5 sm:grid-cols-3">
            {[0, 1, 2].map((index) => (
              <div key={index} className="h-64 animate-pulse rounded-card bg-surface-soft" />
            ))}
          </div>
        ) : featured.length > 0 ? (
          <div className="mt-10 grid gap-5 sm:grid-cols-3">
            {featured.map((consultant, index) => (
              <Reveal key={consultant.id} delay={0.08 * index} className="h-full">
                <ConsultantCard consultant={consultant} />
              </Reveal>
            ))}
          </div>
        ) : (
          <p className="mt-10 text-ink-50">{t('home.consultantsEmpty')}</p>
        )}

        <Reveal delay={0.1} className="mt-8">
          <Link
            to="/consultants"
            className="inline-flex h-11 items-center justify-center rounded-control border border-hairline bg-surface-strong px-6 text-sm font-medium text-ink transition-colors hover:bg-surface"
          >
            {t('home.consultantsCta')}
          </Link>
        </Reveal>
      </Section>

      {/* FAQ — accordion straight from the Dictionaries */}
      <Section band>
        <Reveal className="flex flex-col gap-4">
          <span className="text-sm font-medium text-accent">{t('home.faqKicker')}</span>
          <h2 className="max-w-xl text-balance">{t('home.faqTitle')}</h2>
        </Reveal>
        <div className="mt-8 flex max-w-3xl flex-col gap-3">
          {faqItems.map((item) => (
            <details
              key={item.q}
              className="group rounded-card border border-hairline bg-surface px-6 py-4 open:bg-canvas"
            >
              <summary className="flex cursor-pointer list-none items-center justify-between gap-4 text-base text-ink">
                {item.q}
                <span
                  aria-hidden="true"
                  className="shrink-0 text-xl text-accent transition-transform duration-200 group-open:rotate-45"
                >
                  +
                </span>
              </summary>
              <p className="mt-3 text-sm leading-7 text-ink-70">{item.a}</p>
            </details>
          ))}
        </div>
        <Reveal delay={0.1} className="mt-6">
          <Link
            to="/faq"
            className="w-fit text-sm font-medium text-accent transition-colors hover:text-ink"
          >
            {t('home.faqCta')}
          </Link>
        </Reveal>
      </Section>

      {/* Closing CTA */}
      <section className="bg-tint">
        <Container className="flex flex-col items-center gap-5 py-16 text-center sm:py-20">
          <Reveal className="flex flex-col items-center gap-4">
            <h2 className="max-w-xl text-balance">{t('home.ctaTitle')}</h2>
            <p className="max-w-lg text-ink-70">{t('home.ctaText')}</p>
            <div className="flex flex-wrap justify-center gap-3 pt-2">
              <Link
                to="/consultants"
                className="inline-flex h-11 items-center justify-center rounded-control bg-accent px-6 font-medium text-on-accent shadow-card transition-colors hover:bg-accent-strong"
              >
                {t('home.ctaPrimary')}
              </Link>
              <Link
                to="/contact"
                className="inline-flex h-11 items-center justify-center rounded-control border border-hairline bg-canvas px-6 text-ink transition-colors hover:bg-surface"
              >
                {t('home.ctaSecondary')}
              </Link>
            </div>
          </Reveal>
        </Container>
      </section>
    </div>
  )
}
