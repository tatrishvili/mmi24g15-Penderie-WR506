export type ProfileRole = 'parent' | 'partner' | 'child'

export interface UserSummary {
  id: number
  email: string
}

export interface HouseholdSummary {
  id: number
  name: string
  slug: string
}

export interface ProfileSummary {
  id: number
  name: string
  role: ProfileRole
  avatarColor: string | null
  hasPin: boolean
  householdId: number
}

export interface PlaceSummary {
  id: number
  name: string
  isDefault: boolean
}

export interface RegisterPayload {
  email: string
  password: string
  passwordConfirm: string
  householdName: string
  firstName: string
  placeName?: string
}

export interface RegisterResult {
  user: UserSummary
  household: HouseholdSummary
  profile: ProfileSummary
  place: PlaceSummary
  activeProfileId: number
}

export interface LoginResult {
  user: UserSummary
  household: HouseholdSummary
  profiles: ProfileSummary[]
  activeProfileId: null
}

export interface MeResult {
  household: HouseholdSummary
  profiles: ProfileSummary[]
  places: PlaceSummary[]
  activeProfileId: number | null
  activeProfile: ProfileSummary | null
  user?: UserSummary
}

export interface CreateChildPayload {
  role: 'child'
  name: string
  pin: string
  avatarColor?: string
}

export interface CreatePartnerPayload {
  role: 'partner'
  name: string
  email: string
  password: string
  pin?: string
  avatarColor?: string
}

export interface HouseholdProfilesResult {
  household: { name: string; slug: string }
  profiles: ProfileSummary[]
}

export function roleLabel(role: ProfileRole): string {
  switch (role) {
    case 'parent':
      return 'Parent'
    case 'partner':
      return 'Conjoint'
    case 'child':
      return 'Enfant'
  }
}
