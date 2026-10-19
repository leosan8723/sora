import { useCallback, useState } from 'react'
import type { ReactNode } from 'react'

import { applyTheme, initialTheme, setStoredTheme } from './theme'
import type { Theme } from './theme'
import { ThemeContext } from './themeContext'
import type { ThemeContextValue } from './themeContext'

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [theme, setThemeState] = useState<Theme>(() => {
    const initial = initialTheme()
    // Re-apply in case the boot script in index.html could not run.
    applyTheme(initial)
    return initial
  })

  const setTheme = useCallback((next: Theme) => {
    setThemeState(next)
    setStoredTheme(next)
    applyTheme(next)
  }, [])

  const toggle = useCallback(() => {
    setThemeState((previous) => {
      const next: Theme = previous === 'dark' ? 'light' : 'dark'
      setStoredTheme(next)
      applyTheme(next)
      return next
    })
  }, [])

  const value: ThemeContextValue = { theme, setTheme, toggle }

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>
}
