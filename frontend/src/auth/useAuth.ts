import { useContext } from 'react'
import { AuthContext } from './AuthContext.tsx'
import type { AuthContextValue } from './AuthContext.tsx'

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)

  if (context === null) {
    throw new Error('useAuth must be used inside <AuthProvider>')
  }

  return context
}
