import { apiRequest } from '../../lib/api'
import type { AuthUser } from '../auth/types'

export interface UpdateProfilePayload {
  name: string
  phone?: string | null
}

export interface ChangePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
}

export function updateProfile(
  payload: UpdateProfilePayload,
): Promise<AuthUser> {
  return apiRequest<AuthUser>('/auth/profile', {
    method: 'PUT',
    body: {
      name: payload.name.trim(),
      phone: payload.phone?.trim() ? payload.phone.trim() : null,
    },
  })
}

/** The server responds with 204 No Content on success. */
export function changePassword(
  payload: ChangePasswordPayload,
): Promise<null> {
  return apiRequest<null>('/auth/password', {
    method: 'PUT',
    body: payload,
  })
}
