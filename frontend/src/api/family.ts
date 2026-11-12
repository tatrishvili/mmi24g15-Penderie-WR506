import { api } from './client.ts'
import type { ProfileSummary } from '../types/auth.ts'
import type { ItemSummary } from '../types/item.ts'

export interface ParentLink {
  id: number
  name: string | null
  relationship: 'mother' | 'father'
  state: string
  canRevoke?: boolean
}
export interface ManagedChild {
  profile: ProfileSummary
  username: string | null
  enabled: boolean
  parents: ParentLink[]
}
export interface FamilyMember {
  id: number
  name: string
  role: string
}
export interface ChildCollection {
  id: number
  name: string
  itemIds: number[]
  version: number
}
export interface PreviewItem {
  id?: number
  version?: number
  name: string
  type?: string | null
  color?: string | null
  brand?: string | null
  author?: string | null
  artist?: string | null
  publisher?: string | null
  condition?: string
  photos: { id?: number; url: string }[]
}
export interface Proposal {
  recipientNames?: Record<string, string>
  itemId?: number
  audience?: string
  profileIds?: number[]
  recipients?: (string | { id: number; name: string })[]
  preview?: PreviewItem
  snapshot?: { name: string; kind: string; sourceId: number; version: number; items: PreviewItem[] }
}
export interface Approval {
  id: number
  version: number
  kind: string
  childName: string
  status: string
  proposal: Proposal
}
export interface Connection {
  id: number
  requesterKey: string
  recipientKey: string
  requesterId: number
  requesterName: string
  recipientId: number | null
  recipientName: string
  relative: boolean
  state: string
  requesterApproved: boolean
  recipientApproved: boolean
  recipientAccepted: boolean
}
export interface NoticeDetail {
  id: number
  version?: number
  kind?: string
  childName?: string
  status?: string
  proposal?: Proposal
  reviewable?: boolean
  canDecide?: boolean
  state?: string
  relationship?: string
  requesterKey?: string
  recipientKey?: string
  requesterId?: number
  requesterName?: string
  recipientName?: string
  recipientId?: number
  requesterApproved?: boolean
  recipientApproved?: boolean
  recipientAccepted?: boolean
}
export interface Notification {
  id: number
  kind: string
  referenceId: number
  read: boolean
  createdAt: string
  detail: NoticeDetail | null
}
export interface Inbox {
  notifications: Notification[]
  unread: number
  nextBefore: number | null
}
export interface Publication {
  id: number
  canRevoke: boolean
  name: string
  kind: string
  items: PreviewItem[]
}
export const listChildren = () => api<{ children: ManagedChild[] }>('/api/children')
export const familyMembers = () => api<{ members: FamilyMember[] }>('/api/family/members')
export const setupChild = (body: {
  name: string
  age: number
  username: string
  password: string
  relationship: string
  profileId?: number
}) => api<{ profileId: number; username: string }>('/api/children', { method: 'POST', body })
export const setChildAccess = (id: number, enabled: boolean) =>
  api(`/api/children/${id}/access`, { method: 'PATCH', body: { enabled } })
export const resetChildPassword = (id: number, password: string) =>
  api(`/api/children/${id}/credentials`, { method: 'POST', body: { password } })
export const changeChildPassword = (currentPassword: string, password: string) =>
  api('/api/auth/child-password', { method: 'POST', body: { currentPassword, password } })
export const inviteParent = (id: number, username: string, relationship: string) =>
  api(`/api/children/${id}/parents`, { method: 'POST', body: { username, relationship } })
export const acceptParent = (id: number) =>
  api(`/api/parent-invitations/${id}/accept`, { method: 'POST' })
export const revokeParent = (id: number) => api(`/api/parent-links/${id}`, { method: 'DELETE' })
export const familyAudience = (
  id: number,
  body: { audience: string; profileIds: number[]; version: number; approved: boolean },
) =>
  api<{ item: ItemSummary; approval: Approval | null }>(`/api/items/${id}/family-audience`, {
    method: 'PUT',
    body,
  })
export const inbox = (before?: number) =>
  api<Inbox>(`/api/notifications${before ? `?before=${before}` : ''}`)
export const readNotification = (id: number) =>
  api(`/api/notifications/${id}/read`, { method: 'POST' })
export const decideApproval = (id: number, version: number, approve: boolean) =>
  api(`/api/child-approvals/${id}/decision`, { method: 'POST', body: { version, approve } })
export const withdrawApproval = (id: number) =>
  api(`/api/child-approvals/${id}`, { method: 'DELETE' })
export const listCollections = (childId?: number) =>
  api<{ collections: ChildCollection[] }>(
    `/api/child-collections${childId ? `?childId=${childId}` : ''}`,
  )
export const saveCollection = (
  body: { name: string; itemIds: number[]; childId?: number; version?: number },
  id?: number,
) =>
  api<{ collection: ChildCollection }>(`/api/child-collections${id ? `/${id}` : ''}`, {
    method: id ? 'PATCH' : 'POST',
    body,
  })
export const listConnections = () => api<{ connections: Connection[] }>('/api/child-connections')
export const requestConnection = (username: string, relative: boolean, childId?: number) =>
  api('/api/child-connections', { method: 'POST', body: { username, relative, childId } })
export const connectionAction = (id: number, action: string) =>
  api(`/api/child-connections/${id}`, { method: 'POST', body: { action } })
export const requestPublication = (body: {
  kind: string
  sourceId: number
  version: number
  recipients: string[]
  childId?: number
}) => api('/api/child-approvals', { method: 'POST', body })
export const listPublications = () =>
  api<{ publications: Publication[] }>('/api/child-publications')
export const revokePublication = (id: number) =>
  api(`/api/child-publications/${id}`, { method: 'DELETE' })

export const declineParent = (id: number) =>
  api(`/api/parent-invitations/${id}/decline`, { method: 'POST' })
