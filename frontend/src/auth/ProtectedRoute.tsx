import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from './useAuth.ts'

export function ProtectedRoute({ children }: { children: ReactNode }) {
  const { status } = useAuth()
  const location = useLocation()

  if (status === 'loading') {
    return (
      <div className="auth-page">
        <p>Chargement…</p>
      </div>
    )
  }

  if (status === 'anonymous') {
    return (
      <Navigate to="/connexion" state={{ from: location.pathname + location.search }} replace />
    )
  }

  return <>{children}</>
}
