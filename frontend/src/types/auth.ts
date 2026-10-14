export type ProfileRole = 'parent' | 'partner' | 'child'

export interface UserSummary {
  id: number
  email: string
  username?: string | null
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
  username: string
  email: string
  birthDate: string
  password: string
  passwordConfirm: string
}

export interface RegisterResult {
  message: string
}

export interface LoginResult {
  user: UserSummary
  households: HouseholdSummary[]
  household: HouseholdSummary | null
  profiles: ProfileSummary[]
  places: PlaceSummary[]
  activeHouseholdId: number | null
  activeProfileId: number | null
  activeProfile: ProfileSummary | null
}

export interface MeResult {
  households: HouseholdSummary[]
  household: HouseholdSummary | null
  profiles: ProfileSummary[]
  places: PlaceSummary[]
  activeHouseholdId: number | null
  activeProfileId: number | null
  activeProfile: ProfileSummary | null
  user?: UserSummary
}

export interface CreateHouseholdPayload {
  householdName: string
  firstName: string
  placeName?: string
}

export interface CreateHouseholdResult {
  household: HouseholdSummary
  profile: ProfileSummary
  place: PlaceSummary
  activeHouseholdId: number
  activeProfileId: number
}

export interface CreateChildPayload {
  role: 'child'
  name: string
  pin?: string
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
