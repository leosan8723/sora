import { getAuthToken } from './authToken'
import { currentLocale } from '../features/i18n/localeStore'

export interface ApiErrorItem {
  code: string
  field?: string
  message: string
}

export interface ApiErrorPayload {
  message: string
  errors?: ApiErrorItem[]
}

export class ApiError extends Error {
  readonly status: number
  readonly errors: ApiErrorItem[]

  constructor(status: number, payload: ApiErrorPayload) {
    super(payload.message || 'Request failed')
    this.name = 'ApiError'
    this.status = status
    this.errors = payload.errors ?? []
  }

  /** First validation message reported for a field, if any. */
  fieldMessage(field: string): string | undefined {
    return this.errors.find((error) => error.field === field)?.message
  }
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface Paginated<T> {
  data: T[]
  meta: PaginationMeta
}

const API_BASE_URL: string =
  import.meta.env.VITE_API_BASE_URL ?? 'http://127.0.0.1:8000/api/v1'

export interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  signal?: AbortSignal
  token?: string | null
}

function parseJson(text: string): unknown {
  if (!text) {
    return null
  }

  try {
    return JSON.parse(text) as unknown
  } catch {
    return null
  }
}

function toErrorPayload(value: unknown, fallback: string): ApiErrorPayload {
  if (value && typeof value === 'object') {
    const record = value as Record<string, unknown>
    const message = typeof record.message === 'string' ? record.message : fallback
    const errors = Array.isArray(record.errors)
      ? (record.errors as ApiErrorItem[])
      : undefined

    return { message, errors }
  }

  return { message: fallback }
}

async function performRequest(
  path: string,
  options: RequestOptions = {},
): Promise<unknown> {
  const { method = 'GET', body, signal, token } = options

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Accept-Language': currentLocale(),
  }
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  const resolvedToken = token === undefined ? getAuthToken() : token
  if (resolvedToken) {
    headers.Authorization = `Bearer ${resolvedToken}`
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    method,
    headers,
    signal,
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  const payload = parseJson(await response.text())

  if (!response.ok) {
    throw new ApiError(
      response.status,
      toErrorPayload(payload, response.statusText),
    )
  }

  return payload
}

export async function apiRequest<T>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  const payload = await performRequest(path, options)

  if (payload && typeof payload === 'object' && 'data' in payload) {
    return (payload as { data: T }).data
  }

  return payload as T
}

/** Request a paginated collection, preserving the pagination metadata. */
export async function apiRequestPage<T>(
  path: string,
  options: RequestOptions = {},
): Promise<Paginated<T>> {
  const payload = await performRequest(path, options)

  if (payload && typeof payload === 'object' && 'data' in payload) {
    const record = payload as { data: T[]; meta?: PaginationMeta }
    return { data: record.data, meta: record.meta as PaginationMeta }
  }

  return { data: [], meta: emptyMeta() }
}

export interface Envelope<T, M> {
  data: T
  meta: M
}

/** Request a resource that returns a `data` + `meta` envelope. */
export async function apiRequestEnvelope<T, M>(
  path: string,
  options: RequestOptions = {},
): Promise<Envelope<T, M>> {
  const payload = await performRequest(path, options)

  if (payload && typeof payload === 'object' && 'data' in payload) {
    const record = payload as { data: T; meta?: M }
    return { data: record.data, meta: record.meta as M }
  }

  return { data: payload as T, meta: undefined as M }
}

function emptyMeta(): PaginationMeta {
  return {
    current_page: 1,
    last_page: 1,
    per_page: 0,
    total: 0,
    from: null,
    to: null,
  }
}

/** Human-readable message for any thrown value. */
export function errorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    return error.message || fallback
  }
  if (error instanceof Error && error.message) {
    return error.message
  }
  return fallback
}
