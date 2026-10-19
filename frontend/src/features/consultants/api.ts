import { apiRequest, apiRequestPage } from '../../lib/api'
import type { Paginated } from '../../lib/api'
import type {
  ConsultantDetail,
  ConsultantListParams,
  ConsultantSummary,
  Specialty,
} from './types'

function toQuery(params: ConsultantListParams): string {
  const search = new URLSearchParams()

  if (params.q) {
    search.set('q', params.q)
  }
  if (params.specialty) {
    search.set('specialty', params.specialty)
  }
  if (params.sort) {
    search.set('sort', params.sort)
  }
  if (params.page && params.page > 1) {
    search.set('page', String(params.page))
  }
  if (params.perPage) {
    search.set('per_page', String(params.perPage))
  }

  const query = search.toString()

  return query ? `?${query}` : ''
}

export function fetchConsultants(
  params: ConsultantListParams,
  signal?: AbortSignal,
): Promise<Paginated<ConsultantSummary>> {
  return apiRequestPage<ConsultantSummary>(`/consultants${toQuery(params)}`, {
    signal,
  })
}

export function fetchConsultant(
  id: string | number,
  signal?: AbortSignal,
): Promise<ConsultantDetail> {
  return apiRequest<ConsultantDetail>(`/consultants/${id}`, { signal })
}

export function fetchSpecialties(signal?: AbortSignal): Promise<Specialty[]> {
  return apiRequest<Specialty[]>('/specialties', { signal })
}
