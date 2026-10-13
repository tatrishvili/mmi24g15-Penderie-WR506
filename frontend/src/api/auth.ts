import { api } from './client.ts'
import type {
  CreateChildPayload,
  CreatePartnerPayload,
  HouseholdProfilesResult,
  HouseholdSummary,
  LoginResult,
  MeResult,
  ProfileSummary,
  RegisterPayload,
  RegisterResult,
} from '../types/auth.ts'

export function register(payload: RegisterPayload): Promise<RegisterResult> {
  return api<RegisterResult>('/api/auth/register', { method: 'POST', body: payload })
}

export function login(email: string, password: string): Promise<LoginResult> {
  return api<LoginResult>('/api/auth/login', { method: 'POST', body: { email, password } })
}

export function logout(): Promise<void> {
  return api<void>('/api/auth/logout', { method: 'POST' })
}

export function me(): Promise<MeResult> {
  return api<MeResult>('/api/auth/me')
}

export function householdProfiles(slug: string): Promise<HouseholdProfilesResult> {
  return api<HouseholdProfilesResult>(`/api/auth/households/${encodeURIComponent(slug)}/profiles`)
}

export function pinLogin(
  householdSlug: string,
  profileId: number,
  pin: string,
): Promise<{ profile: ProfileSummary; household: HouseholdSummary; activeProfileId: number }> {
  return api('/api/auth/profile-pin', { method: 'POST', body: { householdSlug, profileId, pin } })
}

export function createProfile(
  payload: CreateChildPayload | CreatePartnerPayload,
): Promise<{ profile: ProfileSummary }> {
  return api<{ profile: ProfileSummary }>('/api/auth/profiles', { method: 'POST', body: payload })
}

export function switchProfile(
  profileId: number,
): Promise<{ profile: ProfileSummary; activeProfileId: number }> {
  return api(`/api/auth/profiles/${profileId}/switch`, { method: 'POST' })
}
