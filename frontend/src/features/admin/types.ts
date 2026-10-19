export interface AdminSummary {
  users: {
    total: number
    clients: number
    consultants: number
    admins: number
  }
  consultants: {
    profiles: number
    verified: number
    discoverable: number
  }
  appointments: {
    total: number
    pending: number
    confirmed: number
    completed: number
    cancelled: number
    upcoming_7d: number
  }
  contact_messages: {
    unhandled: number
  }
}

export type AdminRole = 'client' | 'consultant' | 'admin'

export interface AdminUser {
  id: number
  name: string
  email: string
  phone: string | null
  role: AdminRole
  is_active: boolean
  email_verified_at: string | null
  created_at: string | null
  /** false while the account is waiting for its owner to complete the setup link */
  password_set?: boolean
  password_set_at?: string | null
  consultant?: {
    id: number
    headline: string | null
    is_verified: boolean
    is_active: boolean
  } | null
}

export interface AdminConsultant {
  id: number
  user?: {
    id: number
    name: string
    email: string
    is_active: boolean
  }
  headline: string | null
  bio: string | null
  /** English content; null means "not provided yet" (fa renders as fallback) */
  name_en?: string | null
  headline_en?: string | null
  bio_en?: string | null
  /** Profile fields that still lack an English translation */
  translation_missing_en?: string[]
  years_experience: number | null
  is_verified: boolean
  is_active: boolean
  consultation_types_count?: number
  appointments_count?: number
}

export type AdminConsultationMode = 'online' | 'in_person'

export interface AdminConsultationType {
  id: number
  consultant?: {
    id: number
    name: string | null
  }
  title: string
  description: string | null
  duration_minutes: number
  price_toman: number
  /** Integer cents; null means "not set yet" and the panel must flag it. */
  price_usd_cents: number | null
  mode: AdminConsultationMode
  is_active: boolean
  sort_order: number
}

export interface AdminContactMessage {
  id: number
  user_id: number | null
  name: string
  email: string
  subject: string
  body: string
  status: 'new' | 'handled'
  created_at: string | null
}

export interface AdminSettings {
  supported_modes: AdminConsultationMode[]
  contact_lines: string[]
}

export interface AdminUserListParams {
  role?: AdminRole
  q?: string
  page?: number
}

export interface AdminAppointmentListParams {
  status?: string
  mode?: AdminConsultationMode
  page?: number
}

export interface AdminConsultantListParams {
  q?: string
  page?: number
}

export interface CreateConsultantPayload {
  name: string
  email: string
  /** Omitted when the consultant should set their password via the setup link */
  password?: string
  phone?: string | null
}
