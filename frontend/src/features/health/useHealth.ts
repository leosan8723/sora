import { useQuery } from '@tanstack/react-query'

import { apiRequest } from '../../lib/api'

export interface HealthStatus {
  status: string
  application: string
  environment: string
  time_utc: string
  time_tehran: string
  timezone: string
  display_timezone: string
  database: string
}

export function useHealth() {
  return useQuery({
    queryKey: ['health'],
    queryFn: ({ signal }) => apiRequest<HealthStatus>('/health', { signal }),
  })
}
