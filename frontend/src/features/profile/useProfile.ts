import { useMutation } from '@tanstack/react-query'

import { changePassword, updateProfile } from './api'
import type { ChangePasswordPayload, UpdateProfilePayload } from './api'

export function useUpdateProfile() {
  return useMutation({
    mutationFn: (payload: UpdateProfilePayload) => updateProfile(payload),
  })
}

export function useChangePassword() {
  return useMutation({
    mutationFn: (payload: ChangePasswordPayload) => changePassword(payload),
  })
}
