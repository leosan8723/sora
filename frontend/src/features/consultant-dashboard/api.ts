import { apiRequest } from '../../lib/api'
import type {
  AvailabilityRulePayload,
  ConsultationTypePayload,
  ManagedAvailabilityRule,
  ManagedConsultationType,
  OwnerProfile,
  ProfilePayload,
} from './types'

export function fetchOwnerProfile(signal?: AbortSignal): Promise<OwnerProfile> {
  return apiRequest<OwnerProfile>('/consultant/profile', { signal })
}

export function updateOwnerProfile(
  payload: ProfilePayload,
): Promise<OwnerProfile> {
  return apiRequest<OwnerProfile>('/consultant/profile', {
    method: 'PUT',
    body: payload,
  })
}

export function syncOwnerSpecialties(ids: number[]): Promise<OwnerProfile> {
  return apiRequest<OwnerProfile>('/consultant/specialties', {
    method: 'PUT',
    body: { specialties: ids },
  })
}

export function fetchOwnerTypes(
  signal?: AbortSignal,
): Promise<ManagedConsultationType[]> {
  return apiRequest<ManagedConsultationType[]>('/consultant/consultation-types', {
    signal,
  })
}

export function createOwnerType(
  payload: ConsultationTypePayload,
): Promise<ManagedConsultationType> {
  return apiRequest<ManagedConsultationType>('/consultant/consultation-types', {
    method: 'POST',
    body: payload,
  })
}

export function updateOwnerType(
  id: number,
  payload: ConsultationTypePayload,
): Promise<ManagedConsultationType> {
  return apiRequest<ManagedConsultationType>(
    `/consultant/consultation-types/${id}`,
    { method: 'PUT', body: payload },
  )
}

export async function deleteOwnerType(id: number): Promise<void> {
  await apiRequest<null>(`/consultant/consultation-types/${id}`, {
    method: 'DELETE',
  })
}

export function fetchOwnerAvailability(
  signal?: AbortSignal,
): Promise<ManagedAvailabilityRule[]> {
  return apiRequest<ManagedAvailabilityRule[]>('/consultant/availability', {
    signal,
  })
}

export function createOwnerAvailability(
  payload: AvailabilityRulePayload,
): Promise<ManagedAvailabilityRule> {
  return apiRequest<ManagedAvailabilityRule>('/consultant/availability', {
    method: 'POST',
    body: payload,
  })
}

export function updateOwnerAvailability(
  id: number,
  payload: AvailabilityRulePayload,
): Promise<ManagedAvailabilityRule> {
  return apiRequest<ManagedAvailabilityRule>(`/consultant/availability/${id}`, {
    method: 'PUT',
    body: payload,
  })
}

export async function deleteOwnerAvailability(id: number): Promise<void> {
  await apiRequest<null>(`/consultant/availability/${id}`, { method: 'DELETE' })
}
