export interface Specialty {
  id: number
  name: string
  slug: string
  description: string | null
}

export const CONSULTATION_MODES = ['online', 'in_person'] as const

export type ConsultationMode = (typeof CONSULTATION_MODES)[number]

export interface ConsultationType {
  id: number
  title: string
  description: string | null
  duration_minutes: number
  price_toman: number
  /** Integer cents; null until the administrator (or consultant) sets it. */
  price_usd_cents?: number | null
  mode: ConsultationMode
}

export interface AvailabilityRange {
  weekday: number
  start_time: string
  end_time: string
}

export interface AvailabilityDay {
  weekday: number
  ranges: AvailabilityRange[]
}

export interface ConsultantSummary {
  id: number
  name: string
  avatar_path: string | null
  headline: string | null
  years_experience: number | null
  is_verified: boolean
  specialties: Specialty[]
  starting_price_toman: number | null
  /** Minimum USD fee actually configured, else null (never a converted value). */
  starting_price_usd_cents?: number | null
  avg_rating?: number | null
  reviews_count?: number | null
}

export interface ConsultantDetail extends ConsultantSummary {
  bio: string | null
  consultation_types: ConsultationType[]
  availability: AvailabilityDay[]
}

export type ConsultantSort = 'name' | 'experience' | 'newest'

export interface ConsultantListParams {
  q?: string
  specialty?: string
  sort?: ConsultantSort
  page?: number
  perPage?: number
}
