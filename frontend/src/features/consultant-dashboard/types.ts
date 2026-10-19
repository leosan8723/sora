import type { AvailabilityRange, Specialty } from '../consultants/types'

export interface ManagedConsultationType {
  id: number
  title: string
  description: string | null
  duration_minutes: number
  price_toman: number
  price_usd_cents?: number | null
  is_active: boolean
  sort_order: number
}

export interface OwnerProfile {
  id: number
  user_id: number
  headline: string | null
  bio: string | null
  name_en?: string | null
  headline_en?: string | null
  bio_en?: string | null
  years_experience: number | null
  is_verified: boolean
  is_active: boolean
  verified_at: string | null
  specialties: Specialty[]
  consultation_types: ManagedConsultationType[]
  availability_rules: AvailabilityRange[]
}

export interface ManagedAvailabilityRule {
  id: number
  weekday: number
  start_time: string
  end_time: string
  is_active: boolean
}

export interface AvailabilityRulePayload {
  weekday: number
  start_time: string
  end_time: string
  is_active?: boolean
}

export interface ProfilePayload {
  headline: string | null
  bio: string | null
  years_experience: number | null
  name_en?: string | null
  headline_en?: string | null
  bio_en?: string | null
}

export interface ConsultationTypePayload {
  title: string
  description: string | null
  duration_minutes: number
  price_toman: number
  /** Integer cents; null clears the USD fee. */
  price_usd_cents?: number | null
  is_active?: boolean
  sort_order?: number
}
