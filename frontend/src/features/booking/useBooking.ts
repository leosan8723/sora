import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  cancelAppointment,
  createAppointment,
  fetchAppointment,
  fetchAppointments,
  fetchSlots,
  rescheduleAppointment,
  updateAppointmentStatus,
} from './api'
import type {
  AppointmentListParams,
  AppointmentStatus,
  CancelAppointmentPayload,
  CreateAppointmentPayload,
} from './types'

export function slotsQueryKey(consultantId: number, consultationTypeId: number) {
  return ['slots', consultantId, consultationTypeId] as const
}

export function appointmentQueryKey(id: string | number) {
  return ['appointment', String(id)] as const
}

export function appointmentsQueryKey(params: AppointmentListParams = {}) {
  return ['appointments', params] as const
}

export function useAppointments(params: AppointmentListParams = {}) {
  return useQuery({
    queryKey: appointmentsQueryKey(params),
    queryFn: ({ signal }) => fetchAppointments(params, signal),
    placeholderData: keepPreviousData,
  })
}

export function useSlots(consultantId: number, consultationTypeId: number | null) {
  return useQuery({
    queryKey: slotsQueryKey(consultantId, consultationTypeId ?? 0),
    queryFn: ({ signal }) =>
      fetchSlots(consultantId, consultationTypeId as number, signal),
    enabled:
      consultantId > 0 &&
      consultationTypeId !== null &&
      consultationTypeId > 0,
    staleTime: 30_000,
  })
}

export function useAppointment(id: string | number) {
  return useQuery({
    queryKey: appointmentQueryKey(id),
    queryFn: ({ signal }) => fetchAppointment(id, signal),
  })
}

export function useCreateAppointment() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CreateAppointmentPayload) =>
      createAppointment(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['slots'] })
      void queryClient.invalidateQueries({ queryKey: ['appointments'] })
    },
  })
}

export function useUpdateAppointmentStatus() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({
      id,
      status,
    }: {
      id: string | number
      status: AppointmentStatus
    }) => updateAppointmentStatus(id, status),
    onSuccess: (appointment) => {
      queryClient.setQueryData(appointmentQueryKey(appointment.id), appointment)
      void queryClient.invalidateQueries({ queryKey: ['appointments'] })
    },
  })
}

export function useCancelAppointment(id: string | number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CancelAppointmentPayload) =>
      cancelAppointment(Number(id), payload),
    onSuccess: (appointment) => {
      queryClient.setQueryData(appointmentQueryKey(id), appointment)
      void queryClient.invalidateQueries({ queryKey: ['slots'] })
      void queryClient.invalidateQueries({ queryKey: ['appointments'] })
    },
  })
}

export function useRescheduleAppointment(id: string | number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (startsAt: string) =>
      rescheduleAppointment(Number(id), { starts_at: startsAt }),
    onSuccess: (appointment) => {
      queryClient.setQueryData(appointmentQueryKey(id), appointment)
      void queryClient.invalidateQueries({ queryKey: ['slots'] })
      void queryClient.invalidateQueries({ queryKey: ['appointments'] })
    },
  })
}
