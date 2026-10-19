import { apiRequest, apiRequestPage } from '../../lib/api'
import type { Paginated } from '../../lib/api'
import type { Appointment } from '../booking/types'
import type {
  AdminAppointmentListParams,
  AdminConsultant,
  AdminConsultantListParams,
  AdminConsultationMode,
  AdminConsultationType,
  AdminContactMessage,
  AdminSettings,
  AdminSummary,
  AdminUser,
  AdminUserListParams,
  CreateConsultantPayload,
} from './types'

function toQuery(params: object): string {
  const search = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') {
      continue
    }
    search.set(key, String(value))
  }
  const query = search.toString()
  return query ? `?${query}` : ''
}

export function fetchAdminSummary(signal?: AbortSignal): Promise<AdminSummary> {
  return apiRequest<AdminSummary>('/admin/summary', { signal })
}

export function fetchAdminUsers(
  params: AdminUserListParams,
  signal?: AbortSignal,
): Promise<Paginated<AdminUser>> {
  return apiRequestPage<AdminUser>(`/admin/users${toQuery(params)}`, { signal })
}

export function createAdminConsultant(
  payload: CreateConsultantPayload,
): Promise<AdminUser> {
  const body =
    payload.password === undefined || payload.password === ''
      ? { ...payload, password: undefined }
      : payload
  return apiRequest<AdminUser>('/admin/users', { method: 'POST', body })
}

export interface PasswordLinkResponse {
  setup_url: string
  expires_in_minutes: number
}

export function requestAdminPasswordLink(id: number): Promise<PasswordLinkResponse> {
  return apiRequest<PasswordLinkResponse>(`/admin/users/${id}/password-link`, {
    method: 'POST',
  })
}

export function updateAdminUser(
  id: number,
  payload: { name?: string; phone?: string | null; is_active?: boolean; role?: string },
): Promise<AdminUser> {
  return apiRequest<AdminUser>(`/admin/users/${id}`, { method: 'PUT', body: payload })
}

export function fetchAdminConsultants(
  params: AdminConsultantListParams,
  signal?: AbortSignal,
): Promise<Paginated<AdminConsultant>> {
  return apiRequestPage<AdminConsultant>(`/admin/consultants${toQuery(params)}`, {
    signal,
  })
}

export function updateAdminConsultant(
  id: number,
  payload: { is_verified?: boolean; is_active?: boolean; headline?: string | null },
): Promise<AdminConsultant> {
  return apiRequest<AdminConsultant>(`/admin/consultants/${id}`, {
    method: 'PUT',
    body: payload,
  })
}

export function fetchAdminAppointments(
  params: AdminAppointmentListParams,
  signal?: AbortSignal,
): Promise<Paginated<Appointment>> {
  return apiRequestPage<Appointment>(`/admin/appointments${toQuery(params)}`, {
    signal,
  })
}

export function fetchAdminTypes(
  params: { page?: number },
  signal?: AbortSignal,
): Promise<Paginated<AdminConsultationType>> {
  return apiRequestPage<AdminConsultationType>(
    `/admin/consultation-types${toQuery(params)}`,
    { signal },
  )
}

export function updateAdminType(
  id: number,
  payload: {
    title?: string
    price_toman?: number
    duration_minutes?: number
    mode?: AdminConsultationMode
    is_active?: boolean
  },
): Promise<AdminConsultationType> {
  return apiRequest<AdminConsultationType>(`/admin/consultation-types/${id}`, {
    method: 'PUT',
    body: payload,
  })
}

export function fetchAdminMessages(
  params: { status?: string; page?: number },
  signal?: AbortSignal,
): Promise<Paginated<AdminContactMessage>> {
  return apiRequestPage<AdminContactMessage>(
    `/admin/contact-messages${toQuery(params)}`,
    { signal },
  )
}

export function updateAdminMessage(
  id: number,
  status: 'new' | 'handled',
): Promise<AdminContactMessage> {
  return apiRequest<AdminContactMessage>(`/admin/contact-messages/${id}`, {
    method: 'PUT',
    body: { status },
  })
}

export function fetchAdminSettings(signal?: AbortSignal): Promise<AdminSettings> {
  return apiRequest<AdminSettings>('/admin/settings', { signal })
}

export function updateAdminSettings(payload: {
  supported_modes?: AdminConsultationMode[]
  contact_lines?: string[]
}): Promise<AdminSettings> {
  return apiRequest<AdminSettings>('/admin/settings', { method: 'PUT', body: payload })
}
