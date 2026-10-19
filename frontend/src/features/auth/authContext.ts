import { createContext } from 'react'

import type { AuthUser, LoginPayload, RegisterPayload } from './types'

export type AuthStatus = 'loading' | 'authenticated' | 'guest'

export interface AuthContextValue {
  user: AuthUser | null
  status: AuthStatus
  login: (payload: LoginPayload) => Promise<AuthUser>
  register: (payload: RegisterPayload) => Promise<AuthUser>
  logout: () => Promise<void>
  refresh: () => Promise<void>
  /** Patches the cached user (e.g. after a verification resend). */
  updateUser: (patch: Partial<AuthUser>) => void
}

export const AuthContext = createContext<AuthContextValue | null>(null)
