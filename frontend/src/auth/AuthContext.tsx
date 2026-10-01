import { createContext, useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import * as authApi from '../api/auth.ts'
import { ApiError } from '../api/client.ts'
import type {
  CreateChildPayload,
  CreatePartnerPayload,
  HouseholdSummary,
  MeResult,
  PlaceSummary,
  ProfileSummary,
  RegisterPayload,
  UserSummary,
} from '../types/auth.ts'

type AuthStatus = 'loading' | 'anonymous' | 'authenticated'

export interface AuthContextValue {
  status: AuthStatus
  user: UserSummary | null
  household: HouseholdSummary | null
  profiles: ProfileSummary[]
  places: PlaceSummary[]
  activeProfileId: number | null
  activeProfile: ProfileSummary | null
  refresh: () => Promise<void>
  register: (payload: RegisterPayload) => Promise<void>
  login: (email: string, password: string) => Promise<void>
  pinLogin: (householdSlug: string, profileId: number, pin: string) => Promise<void>
  logout: () => Promise<void>
  switchProfile: (profileId: number) => Promise<void>
  createProfile: (payload: CreateChildPayload | CreatePartnerPayload) => Promise<ProfileSummary>
}

const AuthContext = createContext<AuthContextValue | null>(null)

export { AuthContext }

function emptyState() {
  return {
    user: null as UserSummary | null,
    household: null as HouseholdSummary | null,
    profiles: [] as ProfileSummary[],
    places: [] as PlaceSummary[],
    activeProfileId: null as number | null,
    activeProfile: null as ProfileSummary | null,
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<AuthStatus>('loading')
  const [state, setState] = useState(emptyState)

  const applyMe = useCallback((data: MeResult) => {
    setState({
      user: data.user ?? null,
      household: data.household,
      profiles: data.profiles,
      places: data.places,
      activeProfileId: data.activeProfileId,
      activeProfile: data.activeProfile,
    })
    setStatus('authenticated')
  }, [])

  const refresh = useCallback(async () => {
    try {
      applyMe(await authApi.me())
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        setState(emptyState())
        setStatus('anonymous')
        return
      }
      throw error
    }
  }, [applyMe])

  useEffect(() => {
    let cancelled = false

    authApi
      .me()
      .then((data) => {
        if (!cancelled) applyMe(data)
      })
      .catch((error: unknown) => {
        if (cancelled) return
        if (error instanceof ApiError && error.status === 401) {
          setStatus('anonymous')
          return
        }
        // API down or unexpected error: stay anonymous, pages show their own error if needed.
        setStatus('anonymous')
      })

    return () => {
      cancelled = true
    }
  }, [applyMe])

  const register = useCallback(
    async (payload: RegisterPayload) => {
      await authApi.register(payload)
      await refresh()
    },
    [refresh],
  )

  const login = useCallback(
    async (email: string, password: string) => {
      await authApi.login(email, password)
      await refresh()
    },
    [refresh],
  )

  const pinLogin = useCallback(
    async (householdSlug: string, profileId: number, pin: string) => {
      await authApi.pinLogin(householdSlug, profileId, pin)
      await refresh()
    },
    [refresh],
  )

  const logout = useCallback(async () => {
    await authApi.logout()
    setState(emptyState())
    setStatus('anonymous')
  }, [])

  const switchProfile = useCallback(
    async (profileId: number) => {
      await authApi.switchProfile(profileId)
      await refresh()
    },
    [refresh],
  )

  const createProfile = useCallback(
    async (payload: CreateChildPayload | CreatePartnerPayload) => {
      const result = await authApi.createProfile(payload)
      await refresh()
      return result.profile
    },
    [refresh],
  )

  const value = useMemo<AuthContextValue>(
    () => ({
      status,
      ...state,
      refresh,
      register,
      login,
      pinLogin,
      logout,
      switchProfile,
      createProfile,
    }),
    [status, state, refresh, register, login, pinLogin, logout, switchProfile, createProfile],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
