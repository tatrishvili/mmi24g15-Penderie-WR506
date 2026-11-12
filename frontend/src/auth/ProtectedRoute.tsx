import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from './useAuth.ts'

export function ProtectedRoute({ children }: { children: ReactNode }) {
  const { status, activeProfile, childAccount } = useAuth()
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

  if (childAccount?.mustChangePassword && location.pathname !== '/enfant/mot-de-passe')
    return <Navigate to="/enfant/mot-de-passe" replace />
  if (
    activeProfile?.role === 'child' &&
    !/^\/(enfant(?:\/.*)?|dressing|scanner|lieux|profils|notifications|partages-famille|articles(?:\/.*)?)$/.test(
      location.pathname,
    )
  )
    return <Navigate to="/enfant" replace />
  return <>{children}</>
}
