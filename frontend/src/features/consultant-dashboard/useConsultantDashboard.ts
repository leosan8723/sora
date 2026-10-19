import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  createOwnerAvailability,
  createOwnerType,
  deleteOwnerAvailability,
  deleteOwnerType,
  fetchOwnerAvailability,
  fetchOwnerProfile,
  fetchOwnerTypes,
  syncOwnerSpecialties,
  updateOwnerAvailability,
  updateOwnerProfile,
  updateOwnerType,
} from './api'
import type {
  AvailabilityRulePayload,
  ConsultationTypePayload,
  ManagedAvailabilityRule,
  ProfilePayload,
} from './types'

export const OWNER_PROFILE_KEY = ['owner-profile'] as const
export const OWNER_TYPES_KEY = ['owner-types'] as const
export const OWNER_AVAILABILITY_KEY = ['owner-availability'] as const

export function useOwnerProfile() {
  return useQuery({
    queryKey: OWNER_PROFILE_KEY,
    queryFn: ({ signal }) => fetchOwnerProfile(signal),
    retry: false,
  })
}

export function useUpdateOwnerProfile() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: ProfilePayload) => updateOwnerProfile(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: OWNER_PROFILE_KEY })
    },
  })
}

export function useSyncOwnerSpecialties() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (ids: number[]) => syncOwnerSpecialties(ids),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: OWNER_PROFILE_KEY })
    },
  })
}

export function useOwnerTypes() {
  return useQuery({
    queryKey: OWNER_TYPES_KEY,
    queryFn: ({ signal }) => fetchOwnerTypes(signal),
  })
}

export function useCreateOwnerType() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: ConsultationTypePayload) => createOwnerType(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: OWNER_TYPES_KEY })
      void queryClient.invalidateQueries({ queryKey: OWNER_PROFILE_KEY })
    },
  })
}

export function useUpdateOwnerType() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: number
      payload: ConsultationTypePayload
    }) => updateOwnerType(id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: OWNER_TYPES_KEY })
      void queryClient.invalidateQueries({ queryKey: OWNER_PROFILE_KEY })
    },
  })
}

export function useDeleteOwnerType() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteOwnerType(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: OWNER_TYPES_KEY })
      void queryClient.invalidateQueries({ queryKey: OWNER_PROFILE_KEY })
    },
  })
}

export function useOwnerAvailability() {
  return useQuery<ManagedAvailabilityRule[]>({
    queryKey: OWNER_AVAILABILITY_KEY,
    queryFn: ({ signal }) => fetchOwnerAvailability(signal),
  })
}

function useInvalidateAvailability() {
  const queryClient = useQueryClient()
  return () => {
    void queryClient.invalidateQueries({ queryKey: OWNER_AVAILABILITY_KEY })
    void queryClient.invalidateQueries({ queryKey: OWNER_PROFILE_KEY })
  }
}

export function useCreateOwnerAvailability() {
  const invalidate = useInvalidateAvailability()
  return useMutation({
    mutationFn: (payload: AvailabilityRulePayload) =>
      createOwnerAvailability(payload),
    onSuccess: invalidate,
  })
}

export function useUpdateOwnerAvailability() {
  const invalidate = useInvalidateAvailability()
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: number
      payload: AvailabilityRulePayload
    }) => updateOwnerAvailability(id, payload),
    onSuccess: invalidate,
  })
}

export function useDeleteOwnerAvailability() {
  const invalidate = useInvalidateAvailability()
  return useMutation({
    mutationFn: (id: number) => deleteOwnerAvailability(id),
    onSuccess: invalidate,
  })
}
