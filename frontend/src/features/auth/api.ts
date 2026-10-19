import { apiRequest } from '../../lib/api'
import type {
  AuthTokenResponse,
  AuthUser,
  LoginPayload,
  RegisterPayload,
} from './types'

export function loginRequest(
  payload: LoginPayload,
): Promise<AuthTokenResponse> {
  return apiRequest<AuthTokenResponse>('/auth/login', {
    method: 'POST',
    body: { device_name: 'web', ...payload },
  })
}

export function registerRequest(
  payload: RegisterPayload,
): Promise<AuthTokenResponse> {
  return apiRequest<AuthTokenResponse>('/auth/register', {
    method: 'POST',
    body: payload,
  })
}

export async function fetchCurrentUser(signal?: AbortSignal) {
  return apiRequest<AuthUser>('/auth/me', { signal })
}

export async function logoutRequest(): Promise<void> {
  await apiRequest<null>('/auth/logout', { method: 'POST' })
}

export function resendVerificationEmail(): Promise<{ email_verified: boolean }> {
  return apiRequest<{ email_verified: boolean }>('/auth/email/resend', {
    method: 'POST',
  })
}

export function verifyEmailRequest(query: {
  id: string
  token: string
  expires: string
}): Promise<{ email_verified: boolean }> {
  const search = new URLSearchParams(query).toString()

  return apiRequest<{ email_verified: boolean }>(`/auth/email/verify?${search}`)
}
