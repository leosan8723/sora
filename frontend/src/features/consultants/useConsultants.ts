import { keepPreviousData, useQuery } from '@tanstack/react-query'

import { fetchConsultant, fetchConsultants, fetchSpecialties } from './api'
import type { ConsultantListParams } from './types'

export function useSpecialties() {
  return useQuery({
    queryKey: ['specialties'],
    queryFn: ({ signal }) => fetchSpecialties(signal),
    staleTime: 5 * 60_000,
  })
}

export function useConsultants(params: ConsultantListParams) {
  return useQuery({
    queryKey: ['consultants', params],
    queryFn: ({ signal }) => fetchConsultants(params, signal),
    placeholderData: keepPreviousData,
  })
}

export function useConsultant(id: string | number) {
  return useQuery({
    queryKey: ['consultant', id],
    queryFn: ({ signal }) => fetchConsultant(id, signal),
  })
}
