import { apiRequest, apiRequestEnvelope, apiRequestPage } from '../../lib/api'
import type { Envelope, Paginated, PaginationMeta } from '../../lib/api'
import type {
  AdminReview,
  AdminReviewReport,
  Review,
  ReviewableAppointment,
  ReviewMetrics,
  ReviewReportStatus,
  ReviewStatus,
  ReviewSubmission,
  ReviewUpdate,
} from './types'

export function fetchReviews(
  consultantId: string | number,
  page: number,
  options: { mine?: boolean; signal?: AbortSignal } = {},
): Promise<Envelope<Review[], ReviewMetrics & PaginationMeta>> {
  const search = new URLSearchParams()
  if (page > 1) {
    search.set('page', String(page))
  }
  if (options.mine) {
    search.set('mine', '1')
  }
  const query = search.toString()
  return apiRequestEnvelope<Review[], ReviewMetrics & PaginationMeta>(
    `/consultants/${consultantId}/reviews${query ? `?${query}` : ''}`,
    { signal: options.signal },
  )
}

export function fetchReviewableAppointments(
  consultantId: string | number,
  signal?: AbortSignal,
): Promise<ReviewableAppointment[]> {
  return apiRequest<ReviewableAppointment[]>(
    `/consultants/${consultantId}/reviewable-appointments`,
    { signal },
  )
}

export function submitReview(
  consultantId: string | number,
  payload: ReviewSubmission,
): Promise<Review> {
  return apiRequest<Review>(`/consultants/${consultantId}/reviews`, {
    method: 'POST',
    body: payload,
  })
}

export function updateReview(id: number, payload: ReviewUpdate): Promise<Review> {
  return apiRequest<Review>(`/reviews/${id}`, { method: 'PUT', body: payload })
}

export function deleteReview(id: number): Promise<{ message: string }> {
  return apiRequest<{ message: string }>(`/reviews/${id}`, { method: 'DELETE' })
}

export function fetchAdminReviews(
  params: { status?: ReviewStatus; page?: number },
  signal?: AbortSignal,
): Promise<Paginated<AdminReview>> {
  const search = new URLSearchParams()
  if (params.status) {
    search.set('status', params.status)
  }
  if (params.page && params.page > 1) {
    search.set('page', String(params.page))
  }
  const query = search.toString()
  return apiRequestPage<AdminReview>(`/admin/reviews${query ? `?${query}` : ''}`, {
    signal,
  })
}

export function updateAdminReview(
  id: number,
  status: ReviewStatus,
): Promise<AdminReview> {
  return apiRequest<AdminReview>(`/admin/reviews/${id}`, {
    method: 'PUT',
    body: { status },
  })
}

export function deleteAdminReview(id: number): Promise<{ message: string }> {
  return apiRequest<{ message: string }>(`/admin/reviews/${id}`, { method: 'DELETE' })
}

export function fetchAdminReviewReports(
  params: { status?: ReviewReportStatus; page?: number },
  signal?: AbortSignal,
): Promise<Paginated<AdminReviewReport>> {
  const search = new URLSearchParams()
  if (params.status) {
    search.set('status', params.status)
  }
  if (params.page && params.page > 1) {
    search.set('page', String(params.page))
  }
  const query = search.toString()
  return apiRequestPage<AdminReviewReport>(`/admin/review-reports${query ? `?${query}` : ''}`, {
    signal,
  })
}

export function resolveAdminReviewReport(
  id: number,
): Promise<AdminReviewReport> {
  return apiRequest<AdminReviewReport>(`/admin/review-reports/${id}/resolve`, {
    method: 'POST',
  })
}

export function dismissAdminReviewReport(
  id: number,
): Promise<AdminReviewReport> {
  return apiRequest<AdminReviewReport>(`/admin/review-reports/${id}/dismiss`, {
    method: 'POST',
  })
}

export function submitReviewReport(
  reviewId: number,
  reason: string,
): Promise<{ data: { id: number; status: string } }> {
  return apiRequest(`/reviews/${reviewId}/report`, { method: 'POST', body: { reason } })
}
