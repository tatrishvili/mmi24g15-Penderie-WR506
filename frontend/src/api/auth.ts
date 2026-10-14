import { api } from './client.ts'
import type {
  CreateChildPayload,
  CreateHouseholdPayload,
  CreateHouseholdResult,
  CreatePartnerPayload,
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

export function verifyEmail(token: string): Promise<LoginResult> {
  return api<LoginResult>('/api/auth/verify-email', {
    method: 'POST',
    body: { token },
  })
}

export function resendVerification(email: string): Promise<{ message: string }> {
  return api<{ message: string }>('/api/auth/resend-verification', { method: 'POST', body: { email } })
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

export function setUsername(username: string): Promise<{ username: string }> {
  return api<{ username: string }>('/api/auth/username', { method: 'PUT', body: { username } })
}

export function createHousehold(payload: CreateHouseholdPayload): Promise<CreateHouseholdResult> {
  return api<CreateHouseholdResult>('/api/auth/households', { method: 'POST', body: payload })
}

export function selectHousehold(
  householdId: number,
): Promise<{ household: HouseholdSummary; activeHouseholdId: number; activeProfileId: number | null }> {
  return api(`/api/auth/households/${householdId}/select`, { method: 'POST' })
}

export function unlockProfileWithPin(
  profileId: number,
  pin: string,
): Promise<{ profile: ProfileSummary; activeProfileId: number }> {
  return api('/api/auth/profile-pin', { method: 'POST', body: { profileId, pin } })
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
