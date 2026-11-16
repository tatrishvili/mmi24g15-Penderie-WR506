import { api } from './client.ts'

export interface MeStats {
  household: { name: string; slug: string } | null
  memberSince: string | null
  profiles: number
  places: number
  items: number
  members: number
  activeLoans: number
  activity30d: number
}

export interface MeActivity {
  id: number
  action: string
  subjectType: string | null
  subjectId: number | null
  path: string | null
  createdAt: string
}

export function fetchMeStats() {
  return api<MeStats>('/api/me/stats')
}

export function fetchMeActivity() {
  return api<MeActivity[]>('/api/me/activity')
}
