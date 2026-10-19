export interface Review {
  id: number
  rating: number
  body: string
  author: string
  is_verified: boolean
  is_own: boolean
  status: ReviewStatus | null
  created_at: string | null
  updated_at: string | null
}

export interface ReviewableAppointment {
  id: number
  starts_at: string | null
  consultation_type_title: string | null
}

export interface ReviewMetrics {
  avg_rating: number | null
  reviews_count: number
}

export interface ReviewSubmission {
  appointment_id: number
  rating: number
  body: string
}

export interface ReviewUpdate {
  rating: number
  body: string
}

export type ReviewStatus = 'pending' | 'approved' | 'rejected' | 'hidden'

export type ReviewReportStatus = 'open' | 'resolved' | 'dismissed'

export interface AdminReviewReport {
  id: number
  reason: string
  status: ReviewReportStatus
  review: {
    id: number
    rating: number
    body: string
    status: ReviewStatus
    client: { id: number; name: string } | null
    consultant: { id: number; name: string | null } | null
  } | null
  reporter: { id: number; name: string; email: string } | null
  created_at: string | null
}

export interface AdminReview {
  id: number
  rating: number
  body: string
  status: ReviewStatus
  client: { id: number; name: string; email: string } | null
  consultant: { id: number; name: string | null } | null
  appointment: { id: number; starts_at: string | null; status: string } | null
  moderated_at: string | null
  created_at: string | null
}
