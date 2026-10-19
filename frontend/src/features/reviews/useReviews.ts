import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { useAuth } from '../auth/useAuth'
import * as api from './api'
import type { ReviewReportStatus, ReviewStatus, ReviewSubmission, ReviewUpdate } from './types'

const REVIEWS_KEY = 'consultant-reviews'

/**
 * Approved reviews, plus — for a signed-in author — their own reviews that are
 * still awaiting moderation.
 */
export function useReviews(consultantId: string | number, page: number) {
  const { user } = useAuth()

  return useQuery({
    queryKey: [REVIEWS_KEY, String(consultantId), page, user?.id ?? null],
    queryFn: ({ signal }) =>
      api.fetchReviews(consultantId, page, { mine: user !== null, signal }),
    enabled: consultantId !== '' && consultantId !== undefined,
    placeholderData: keepPreviousData,
  })
}

/** Completed-but-unreviewed visits, used to anchor a new review. */
export function useReviewableAppointments(consultantId: string | number, enabled: boolean) {
  return useQuery({
    queryKey: [REVIEWS_KEY, 'reviewable', consultantId],
    queryFn: ({ signal }) => api.fetchReviewableAppointments(consultantId, signal),
    enabled,
  })
}

function useReviewInvalidation(consultantId: string | number) {
  const queryClient = useQueryClient()

  return () => {
    void queryClient.invalidateQueries({ queryKey: [REVIEWS_KEY, consultantId] })
    void queryClient.invalidateQueries({ queryKey: ['consultant', String(consultantId)] })
  }
}

export function useSubmitReview(consultantId: string | number) {
  const invalidate = useReviewInvalidation(consultantId)

  return useMutation({
    mutationFn: (payload: ReviewSubmission) => api.submitReview(consultantId, payload),
    onSuccess: invalidate,
  })
}

export function useUpdateReview(consultantId: string | number) {
  const invalidate = useReviewInvalidation(consultantId)

  return useMutation({
    mutationFn: (args: { id: number; payload: ReviewUpdate }) =>
      api.updateReview(args.id, args.payload),
    onSuccess: invalidate,
  })
}

export function useDeleteReview(consultantId: string | number) {
  const invalidate = useReviewInvalidation(consultantId)

  return useMutation({
    mutationFn: (id: number) => api.deleteReview(id),
    onSuccess: invalidate,
  })
}

/**
 * Reviews the current author can manage on this page: their own entries,
 * including any that are still waiting for moderation. Ownership and status
 * come from the API (`is_own`/`status`), never from a client-side guess.
 */
export function useOwnReviews(consultantId: string | number, page: number) {
  const query = useReviews(consultantId, page)

  return {
    ...query,
    ownReviews: query.data?.data.filter((review) => review.is_own) ?? [],
  }
}

export function useAdminReviews(params: { status?: ReviewStatus; page?: number }) {
  return useQuery({
    queryKey: ['admin-reviews', params],
    queryFn: ({ signal }) => api.fetchAdminReviews(params, signal),
    placeholderData: keepPreviousData,
  })
}

export function useUpdateAdminReview() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (args: { id: number; status: ReviewStatus }) =>
      api.updateAdminReview(args.id, args.status),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-reviews'] })
      void queryClient.invalidateQueries({ queryKey: [REVIEWS_KEY] })
    },
  })
}

export function useDeleteAdminReview() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => api.deleteAdminReview(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-reviews'] })
      void queryClient.invalidateQueries({ queryKey: [REVIEWS_KEY] })
    },
  })
}

const REPORTS_KEY = 'admin-review-reports'

export function useAdminReviewReports(params: {
  status?: ReviewReportStatus
  page?: number
}) {
  return useQuery({
    queryKey: [REPORTS_KEY, params],
    queryFn: ({ signal }) => api.fetchAdminReviewReports(params, signal),
    placeholderData: keepPreviousData,
  })
}

function useReportInvalidation(queryClient: ReturnType<typeof useQueryClient>) {
  return () => {
    void queryClient.invalidateQueries({ queryKey: [REPORTS_KEY] })
    void queryClient.invalidateQueries({ queryKey: ['admin-reviews'] })
  }
}

export function useResolveAdminReport() {
  const queryClient = useQueryClient()
  const invalidate = useReportInvalidation(queryClient)

  return useMutation({
    mutationFn: (id: number) => api.resolveAdminReviewReport(id),
    onSuccess: invalidate,
  })
}

export function useDismissAdminReport() {
  const queryClient = useQueryClient()
  const invalidate = useReportInvalidation(queryClient)

  return useMutation({
    mutationFn: (id: number) => api.dismissAdminReviewReport(id),
    onSuccess: invalidate,
  })
}

/** Submit an abuse report for a published review. */
export function useSubmitReviewReport(consultantId: string | number) {
  const queryClient = useQueryClient()
  const reportInvalidate = useReportInvalidation(queryClient)

  return useMutation({
    mutationFn: (payload: { reviewId: number; reason: string }) =>
      api.submitReviewReport(payload.reviewId, payload.reason),
    onSuccess: () => {
      reportInvalidate()
      void queryClient.invalidateQueries({ queryKey: [REVIEWS_KEY, String(consultantId)] })
    },
  })
}
