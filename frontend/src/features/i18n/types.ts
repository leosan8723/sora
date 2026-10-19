export type Locale = 'fa' | 'en'

/** Placeholder vars passed to function-valued dictionary nodes. */
export type Vars = Record<string, string | number>

export type DictNode = string | ((vars: Vars) => string) | unknown[]

export interface Dict {
  [key: string]: DictNode | Dict
}

/** Resolve a dot-path like `'booking.conflict'` inside a dictionary. */
export function resolveNode(
  dict: Dict,
  path: string,
): string | ((vars: Vars) => string) | unknown[] | undefined {
  let current: DictNode | Dict | undefined = dict

  for (const key of path.split('.')) {
    if (typeof current !== 'object' || current === null) {
      return undefined
    }
    current = (current as Dict)[key]
  }

  if (typeof current === 'string' || typeof current === 'function' || Array.isArray(current)) {
    return current
  }

  return undefined
}

/** Interpolate `{name}` placeholders inside a translated string. */
export function interpolate(template: string, vars: Vars): string {
  return template.replace(/\{(\w+)\}/g, (match, key: string) =>
    key in vars ? String(vars[key]) : match,
  )
}
