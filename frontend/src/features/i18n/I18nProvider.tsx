import { useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'

import { createFormat } from './format'
import { fa } from './locales/fa'
import { en } from './locales/en'
import { applyLocaleDocument, initLocale, setStoredLocale } from './localeStore'
import { interpolate, resolveNode } from './types'
import type { Dict, Locale, Vars } from './types'
import { I18nContext } from './i18nContext'

const DICTIONARIES: Record<Locale, Dict> = { fa, en }

export function I18nProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<Locale>(() => {
    const initial = initLocale()
    // Re-apply in case the boot script in index.html could not run.
    applyLocaleDocument(initial)
    return initial
  })

  const setLocale = useCallback((next: Locale) => {
    setLocaleState(next)
    setStoredLocale(next)
    applyLocaleDocument(next)
  }, [])

  const t = useCallback(
    (path: string, vars?: Vars): string => {
      const node =
        resolveNode(DICTIONARIES[locale], path) ?? resolveNode(DICTIONARIES.fa, path)

      if (node === undefined) {
        if (import.meta.env.DEV) {
          console.warn(`i18n: missing translation for "${path}"`)
        }
        return path
      }

      if (typeof node === 'string') {
        return vars ? interpolate(node, vars) : node
      }

      if (typeof node === 'function') {
        return node(vars ?? {})
      }

      // Non-string collection (FAQ items / card lists): callers cast it.
      return node as unknown as string
    },
    [locale],
  )

  const fmt = useMemo(() => createFormat(locale), [locale])
  const value = useMemo(() => ({ locale, setLocale, t, fmt }), [locale, setLocale, t, fmt])

  // Keep the document title and description in the active language.
  useEffect(() => {
    document.title = t('meta.title')
    document.querySelector('meta[name="description"]')?.setAttribute('content', t('meta.description'))
  }, [t])

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>
}
