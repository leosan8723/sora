import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router'

import { Spinner } from '../../components/ui/Spinner'
import type { UserRole } from './types'
import { useAuth } from './useAuth'

interface RequireRoleProps {
  role: UserRole
  children: ReactNode
}

export function RequireRole({ role, children }: RequireRoleProps) {
  const { user, status } = useAuth()
  const location = useLocation()

  if (status === 'loading') {
    return (
      <div className="flex min-h-40 items-center justify-center">
        <Spinner />
      </div>
    )
  }

  if (!user) {
    return (
      <Navigate to="/login" replace state={{ from: location.pathname }} />
    )
  }

  if (user.role !== role) {
    return <Navigate to="/" replace />
  }

  return <>{children}</>
}
