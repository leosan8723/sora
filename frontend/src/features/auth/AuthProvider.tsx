import { useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'

import { getAuthToken, setAuthToken } from '../../lib/authToken'
import { fetchCurrentUser, loginRequest, logoutRequest, registerRequest } from './api'
import { AuthContext } from './authContext'
import type { AuthStatus } from './authContext'
import type { AuthUser, LoginPayload, RegisterPayload } from './types'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [status, setStatus] = useState<AuthStatus>(() =>
    getAuthToken() ? 'loading' : 'guest',
  )

  const refresh = useCallback(async () => {
    if (!getAuthToken()) {
      setUser(null)
      setStatus('guest')
      return
    }

    try {
      const me = await fetchCurrentUser()
      setUser(me)
      setStatus('authenticated')
    } catch {
      setAuthToken(null)
      setUser(null)
      setStatus('guest')
    }
  }, [])

  useEffect(() => {
    if (getAuthToken() === null) {
      return
    }

    // Session restore: the token exists (state already starts as `loading`),
    // so resolve it and ignore the result if the component unmounts first.
    let cancelled = false

    fetchCurrentUser()
      .then((me) => {
        if (!cancelled) {
          setUser(me)
          setStatus('authenticated')
        }
      })
      .catch(() => {
        if (!cancelled) {
          setAuthToken(null)
          setUser(null)
          setStatus('guest')
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  const login = useCallback(async (payload: LoginPayload) => {
    const response = await loginRequest(payload)
    setAuthToken(response.token)
    setUser(response.user)
    setStatus('authenticated')
    return response.user
  }, [])

  const register = useCallback(async (payload: RegisterPayload) => {
    const response = await registerRequest(payload)
    setAuthToken(response.token)
    setUser(response.user)
    setStatus('authenticated')
    return response.user
  }, [])

  const logout = useCallback(async () => {
    if (getAuthToken()) {
      try {
        await logoutRequest()
      } catch {
        /* the token is discarded regardless of the server response */
      }
    }

    setAuthToken(null)
    setUser(null)
    setStatus('guest')
  }, [])

  const updateUser = useCallback((patch: Partial<AuthUser>) => {
    setUser((current) => (current === null ? current : { ...current, ...patch }))
  }, [])

  const value = useMemo(
    () => ({ user, status, login, register, logout, refresh, updateUser }),
    [user, status, login, register, logout, refresh, updateUser],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
