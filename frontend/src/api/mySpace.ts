import { api } from './client.ts'

export interface MySpaceItem {
  id: number
  name: string
  status: string
  photoUrl: string | null
  place: string
  roomId: number | null
  ownerProfileId: number | null
}

export interface MySpacePlace {
  id: number
  name: string
  rooms: { id: number; name: string; itemCount: number }[]
}

export interface MySpaceProfile {
  id: number
  name: string
  role: 'parent' | 'partner' | 'child'
  itemCount: number
}

export interface MySpaceMember {
  id: string
  name: string
  role: 'parent' | 'partner' | 'child'
  lastLoginAt: string | null
}

export interface MySpaceLoan {
  id: number
  itemName: string
  borrower: string
  since: string
  canReturn: boolean
}

export interface MySpaceShare {
  id: number
  itemName: string
  expiresAt: string
}

export interface MySpaceActivity {
  id: number
  action: string
  actor: string
  objectName: string
  createdAt: string
  changes: string[]
  itemId: number | null
  canEdit: boolean
}

export interface MySpaceData {
  household: { id: number; name: string }
  isChild: boolean
  canManage: boolean
  stats: { items: number; places: number; profiles: number; activeLoans: number }
  items: MySpaceItem[]
  places: MySpacePlace[]
  profiles: MySpaceProfile[]
  members: MySpaceMember[]
  loans: MySpaceLoan[]
  shares: MySpaceShare[]
  activity: MySpaceActivity[]
}

export const fetchMySpace = () => api<MySpaceData>('/api/me/space')

export const markLoanReturned = (id: number) =>
  api(`/api/social/loans/${id}/return`, { method: 'POST' })

export const revokeMySpaceShare = (id: number) =>
  api<void>(`/api/social/shares/${id}`, { method: 'DELETE' })

export const dismissMySpaceActivity = (id: number) =>
  api<void>(`/api/me/space/activity/${id}`, { method: 'DELETE' })
