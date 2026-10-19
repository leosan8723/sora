import { apiRequest, apiRequestEnvelope, apiRequestPage } from '../../lib/api'
import type { Envelope, Paginated } from '../../lib/api'
import type {
  Appointment,
  AppointmentListParams,
  AppointmentStatus,
  BookableDay,
  CancelAppointmentPayload,
  CreateAppointmentPayload,
  RescheduleAppointmentPayload,
  SlotsMeta,
} from './types'

function toQuery(params: AppointmentListParams): string {
  const search = new URLSearchParams()

  if (params.scope) {
    search.set('scope', params.scope)
  }
  if (params.status) {
    search.set('status', params.status)
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

export function fetchSlots(
  consultantId: number,
  consultationTypeId: number,
  signal?: AbortSignal,
): Promise<Envelope<BookableDay[], SlotsMeta>> {
  const search = new URLSearchParams({
    consultation_type: String(consultationTypeId),
  })

  return apiRequestEnvelope<BookableDay[], SlotsMeta>(
    `/consultants/${consultantId}/slots?${search.toString()}`,
    { signal },
  )
}

export function createAppointment(
  payload: CreateAppointmentPayload,
): Promise<Appointment> {
  return apiRequest<Appointment>('/appointments', {
    method: 'POST',
    body: payload,
  })
}

export function fetchAppointment(
  id: string | number,
  signal?: AbortSignal,
): Promise<Appointment> {
  return apiRequest<Appointment>(`/appointments/${id}`, { signal })
}

export function cancelAppointment(
  id: number,
  payload: CancelAppointmentPayload,
): Promise<Appointment> {
  return apiRequest<Appointment>(`/appointments/${id}/cancel`, {
    method: 'POST',
    body: payload,
  })
}

export function fetchAppointments(
  params: AppointmentListParams,
  signal?: AbortSignal,
): Promise<Paginated<Appointment>> {
  return apiRequestPage<Appointment>(`/appointments${toQuery(params)}`, {
    signal,
  })
}

export function updateAppointmentStatus(
  id: string | number,
  status: AppointmentStatus,
): Promise<Appointment> {
  return apiRequest<Appointment>(`/appointments/${id}/status`, {
    method: 'POST',
    body: { status },
  })
}

export function rescheduleAppointment(
  id: number,
  payload: RescheduleAppointmentPayload,
): Promise<Appointment> {
  return apiRequest<Appointment>(`/appointments/${id}/reschedule`, {
    method: 'POST',
    body: payload,
  })
}
