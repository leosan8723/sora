import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import * as api from './api'
import type {
  AdminAppointmentListParams,
  AdminConsultant,
  AdminConsultantListParams,
  AdminConsultationMode,
  AdminConsultationType,
  AdminContactMessage,
  AdminSettings,
  AdminUser,
  AdminUserListParams,
  CreateConsultantPayload,
} from './types'

export function useAdminSummary() {
  return useQuery({
    queryKey: ['admin-summary'],
    queryFn: ({ signal }) => api.fetchAdminSummary(signal),
  })
}

export function useAdminUsers(params: AdminUserListParams) {
  return useQuery({
    queryKey: ['admin-users', params],
    queryFn: ({ signal }) => api.fetchAdminUsers(params, signal),
    placeholderData: keepPreviousData,
  })
}

export function useCreateAdminConsultant() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateConsultantPayload) =>
      api.createAdminConsultant(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-users'] })
      void queryClient.invalidateQueries({ queryKey: ['admin-summary'] })
    },
  })
}

export function useUpdateAdminUser() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (args: { id: number; payload: Partial<AdminUser> }) =>
      api.updateAdminUser(args.id, args.payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-users'] })
      void queryClient.invalidateQueries({ queryKey: ['admin-summary'] })
    },
  })
}

export function useAdminPasswordLink() {
  return useMutation({
    mutationFn: (id: number) => api.requestAdminPasswordLink(id),
  })
}

export function useAdminConsultants(params: AdminConsultantListParams) {
  return useQuery({
    queryKey: ['admin-consultants', params],
    queryFn: ({ signal }) => api.fetchAdminConsultants(params, signal),
    placeholderData: keepPreviousData,
  })
}

export function useUpdateAdminConsultant() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (args: {
      id: number
      payload: Partial<
        Pick<AdminConsultant, 'is_verified' | 'is_active' | 'headline' | 'name_en' | 'headline_en' | 'bio_en'>
      >
    }) => api.updateAdminConsultant(args.id, args.payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-consultants'] })
      void queryClient.invalidateQueries({ queryKey: ['admin-summary'] })
      void queryClient.invalidateQueries({ queryKey: ['consultants'] })
    },
  })
}

export function useAdminAppointments(params: AdminAppointmentListParams) {
  return useQuery({
    queryKey: ['admin-appointments', params],
    queryFn: ({ signal }) => api.fetchAdminAppointments(params, signal),
    placeholderData: keepPreviousData,
  })
}

export function useAdminTypes(params: { page?: number }) {
  return useQuery({
    queryKey: ['admin-types', params],
    queryFn: ({ signal }) => api.fetchAdminTypes(params, signal),
    placeholderData: keepPreviousData,
  })
}

export function useUpdateAdminType() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (args: {
      id: number
      payload: Partial<
        Pick<
          AdminConsultationType,
          'title' | 'price_toman' | 'price_usd_cents' | 'is_active'
        > & {
          mode: AdminConsultationMode
          duration_minutes: number
        }
      >
    }) => api.updateAdminType(args.id, args.payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-types'] })
      void queryClient.invalidateQueries({ queryKey: ['consultants'] })
      void queryClient.invalidateQueries({ queryKey: ['consultant'] })
    },
  })
}

export function useAdminMessages(params: { status?: string; page?: number }) {
  return useQuery({
    queryKey: ['admin-messages', params],
    queryFn: ({ signal }) => api.fetchAdminMessages(params, signal),
    placeholderData: keepPreviousData,
  })
}

export function useUpdateAdminMessage() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (args: { id: number; status: AdminContactMessage['status'] }) =>
      api.updateAdminMessage(args.id, args.status),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-messages'] })
      void queryClient.invalidateQueries({ queryKey: ['admin-summary'] })
    },
  })
}

export function useAdminSettings() {
  return useQuery({
    queryKey: ['admin-settings'],
    queryFn: ({ signal }) => api.fetchAdminSettings(signal),
  })
}

export function useUpdateAdminSettings() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: Partial<AdminSettings>) =>
      api.updateAdminSettings(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin-settings'] })
    },
  })
}
