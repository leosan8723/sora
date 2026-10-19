export type AppointmentStatus =
  | 'pending'
  | 'confirmed'
  | 'completed'
  | 'cancelled'

/** Badge tones per appointment status (labels come from i18n). */
export const APPOINTMENT_STATUS_TONES: Record<
  AppointmentStatus,
  'neutral' | 'accent' | 'muted'
> = {
  pending: 'neutral',
  confirmed: 'accent',
  completed: 'muted',
  cancelled: 'muted',
}

export interface BookableSlot {
  starts_at: string
  ends_at: string
  label: string
}

export interface BookableDay {
  date: string
  weekday: number
  slots: BookableSlot[]
}

export interface SlotsMeta {
  timezone: string
  consultant_id: number
  consultation_type_id: number
  duration_minutes: number
  from: string
  to: string
}

export interface AppointmentConsultant {
  id: number
  name: string | null
  headline: string | null
  avatar_path: string | null
}

export interface AppointmentConsultationType {
  id: number
  title: string
  duration_minutes: number
}

export interface AppointmentParty {
  id: number
  name: string | null
}

export interface Appointment {
  id: number
  status: AppointmentStatus
  starts_at: string
  ends_at: string
  starts_at_tehran: string
  ends_at_tehran: string
  duration_minutes: number
  price_toman: number
  /** Integer cents snapshot captured at booking time; null for legacy rows. */
  price_usd_cents?: number | null
  mode: 'online' | 'in_person' | null
  notes: string | null
  cancellation_reason: string | null
  cancelled_at: string | null
  created_at: string | null
  consultant?: AppointmentConsultant
  consultation_type?: AppointmentConsultationType
  client?: AppointmentParty
  can: {
    cancel: boolean
    reschedule: boolean
    confirm: boolean
    complete: boolean
  }
}

export const APPOINTMENT_SCOPES = ['upcoming', 'past', 'all'] as const

export type AppointmentScope = (typeof APPOINTMENT_SCOPES)[number]

export interface AppointmentListParams {
  scope?: AppointmentScope
  status?: AppointmentStatus
  perPage?: number
  page?: number
}

export interface CreateAppointmentPayload {
  consultation_type_id: number
  starts_at: string
  notes?: string | null
}

export interface RescheduleAppointmentPayload {
  starts_at: string
}

export interface CancelAppointmentPayload {
  cancellation_reason?: string | null
}

const TERMINAL_STATUSES: AppointmentStatus[] = ['completed', 'cancelled']

export function isTerminalStatus(status: AppointmentStatus): boolean {
  return TERMINAL_STATUSES.includes(status)
}
