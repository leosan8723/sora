const STORAGE_KEY = 'consultation.theme'

export type Theme = 'light' | 'dark'

/** Read the persisted choice; `null` means "follow the system". */
export function getStoredTheme(): Theme | null {
  if (typeof window === 'undefined') {
    return null
  }
  try {
    return window.localStorage.getItem(STORAGE_KEY) === 'dark'
      ? 'dark'
      : window.localStorage.getItem(STORAGE_KEY) === 'light'
        ? 'light'
        : null
  } catch {
    return null
  }
}

export function setStoredTheme(theme: Theme): void {
  try {
    window.localStorage.setItem(STORAGE_KEY, theme)
  } catch {
    /* storage unavailable; the choice still applies for this session */
  }
}

export function systemTheme(): Theme {
  if (typeof window === 'undefined' || !window.matchMedia) {
    return 'light'
  }
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

export function applyTheme(theme: Theme): void {
  document.documentElement.dataset.theme = theme

  const meta = document.querySelector<HTMLMetaElement>('meta[name="theme-color"]')
  if (meta) {
    meta.setAttribute('content', theme === 'dark' ? '#11140a' : '#ffffff')
  }
}

/** Boot-time theme, matching the inline script in index.html. */
export function initialTheme(): Theme {
  return getStoredTheme() ?? systemTheme()
}
