import type { ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from './useAuth.ts'

export function ProtectedRoute({ children }: { children: ReactNode }) {
  const { status } = useAuth()

  if (status === 'loading') {
    return (
      <div className="auth-page">
        <p>Chargement…</p>
      </div>
    )
  }

  if (status === 'anonymous') {
    return <Navigate to="/connexion" replace />
  }

  return <>{children}</>
}
