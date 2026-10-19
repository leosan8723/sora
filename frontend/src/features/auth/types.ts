export type UserRole = 'client' | 'consultant' | 'admin'

export interface AuthUser {
  id: number
  name: string
  email: string
  phone: string | null
  role: UserRole
  is_active: boolean
  avatar_path: string | null
  email_verified_at: string | null
  /** Convenience flag; mirrors `email_verified_at != null`. */
  email_verified: boolean
  created_at: string | null
}

export interface LoginPayload {
  email: string
  password: string
  device_name?: string
}

export interface RegisterPayload {
  name: string
  email: string
  phone?: string | null
  password: string
  password_confirmation: string
}

export interface AuthTokenResponse {
  token: string
  token_type: string
  user: AuthUser
}
