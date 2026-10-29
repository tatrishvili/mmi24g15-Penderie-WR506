import { createContext, useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import * as authApi from '../api/auth.ts'
import { ApiError } from '../api/client.ts'
import type {
  CreateChildPayload,
  CreateHouseholdPayload,
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
  households: HouseholdSummary[]
  household: HouseholdSummary | null
  profiles: ProfileSummary[]
  places: PlaceSummary[]
  activeHouseholdId: number | null
  activeProfileId: number | null
  activeProfile: ProfileSummary | null
  refresh: () => Promise<void>
  register: (payload: RegisterPayload) => Promise<void>
  verifyEmail: (token: string) => Promise<void>
  resendVerification: (email: string) => Promise<void>
  login: (email: string, password: string) => Promise<void>
  unlockProfileWithPin: (profileId: number, pin: string) => Promise<void>
  logout: () => Promise<void>
  switchProfile: (profileId: number) => Promise<void>
  createProfile: (payload: CreateChildPayload | CreatePartnerPayload) => Promise<ProfileSummary>
  createHousehold: (payload: CreateHouseholdPayload) => Promise<void>
  selectHousehold: (householdId: number) => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

export { AuthContext }

function emptyState() {
  return {
    user: null as UserSummary | null,
    households: [] as HouseholdSummary[],
    household: null as HouseholdSummary | null,
    profiles: [] as ProfileSummary[],
    places: [] as PlaceSummary[],
    activeHouseholdId: null as number | null,
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
      households: data.households ?? [],
      household: data.household,
      profiles: data.profiles ?? [],
      places: data.places ?? [],
      activeHouseholdId: data.activeHouseholdId,
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
    },
    [],
  )

  const verifyEmail = useCallback(
    async (token: string) => {
      await authApi.verifyEmail(token)
      await refresh()
    },
    [refresh],
  )

  const resendVerification = useCallback(async (email: string) => {
    await authApi.resendVerification(email)
  }, [])

  const login = useCallback(
    async (email: string, password: string) => {
      await authApi.login(email, password)
      await refresh()
    },
    [refresh],
  )

  const unlockProfileWithPin = useCallback(
    async (profileId: number, pin: string) => {
      await authApi.unlockProfileWithPin(profileId, pin)
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

  const createHousehold = useCallback(
    async (payload: CreateHouseholdPayload) => {
      await authApi.createHousehold(payload)
      await refresh()
    },
    [refresh],
  )

  const selectHousehold = useCallback(
    async (householdId: number) => {
      await authApi.selectHousehold(householdId)
      await refresh()
    },
    [refresh],
  )

  const value = useMemo<AuthContextValue>(
    () => ({
      status,
      ...state,
      refresh,
      register,
      verifyEmail,
      resendVerification,
      login,
      unlockProfileWithPin,
      logout,
      switchProfile,
      createProfile,
      createHousehold,
      selectHousehold,
    }),
    [
      status,
      state,
      refresh,
      register,
      verifyEmail,
      resendVerification,
      login,
      unlockProfileWithPin,
      logout,
      switchProfile,
      createProfile,
      createHousehold,
      selectHousehold,
    ],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
