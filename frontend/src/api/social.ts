import { api } from './client.ts'

export interface Collection {
  id: string
  name: string
  cover: string
  visibility: CollectionAudience
  itemIds: number[]
}
export interface Workspace {
  profile: { displayName: string; bio: string; photo: string }
  username: string | null
  collections: Collection[]
  pins: string[]
  version: number
}
export interface Share {
  id: number
  itemId: number
  itemName: string
  revoked: boolean
  verifiedOnly: boolean
  expiresAt: string
  createdAt: string
}
export interface Loan {
  id: number
  itemId: number | null
  itemName: string
  owner: string
  borrower: string
  isOwner: boolean
  status: 'pending' | 'accepted' | 'active' | 'returned' | 'declined'
  dueAt: string
  message: string
  requestedAt: string
  acceptedAt: string | null
  handedAt: string | null
  returnedAt: string | null
}
export interface SharedItem {
  photoUrl: string | null
  name: string
  type: string | null
  color: string | null
  owner: string
  available: boolean
}
export const getWorkspace = () => api<Workspace>('/api/social/workspace')
export const updateWorkspace = (body: {
  version: number
  profile?: Workspace['profile']
  pins?: string[]
}) => api<Workspace>('/api/social/workspace', { method: 'PATCH', body })
export const createCollection = (
  name: string,
  cover: string,
  visibility: CollectionAudience = 'personal',
  approved = false,
) =>
  api<Workspace>('/api/social/collections', {
    method: 'POST',
    body: { name, cover, visibility, approved },
  })
export const collectionItem = (id: string, itemId: number, remove = false) =>
  api<Workspace>(`/api/social/collections/${id}/items/${itemId}`, {
    method: remove ? 'DELETE' : 'PUT',
  })
export const setVisibility = (id: number, visibility: string) =>
  api(`/api/social/items/${id}/visibility`, { method: 'PUT', body: { visibility } })
export const listShares = () => api<{ shares: Share[] }>('/api/social/shares')
export const createShare = (id: number, verifiedOnly: boolean, approved: boolean) =>
  api<{ id: number; path: string; expiresAt: string }>(`/api/social/items/${id}/shares`, {
    method: 'POST',
    body: { verifiedOnly, approved },
  })
export const revokeShare = (id: number) =>
  api<void>(`/api/social/shares/${id}`, { method: 'DELETE' })
export const getShared = (token: string) => api<{ item: SharedItem }>(`/api/social/shared/${token}`)
export const listLoans = () => api<{ loans: Loan[] }>('/api/social/loans')
export const requestLoan = (token: string, dueAt: string, message: string) =>
  api<{ loan: Loan }>(`/api/social/shared/${token}/loans`, {
    method: 'POST',
    body: { dueAt, message },
  })
export const transitionLoan = (id: number, action: string) =>
  api<{ loan: Loan }>(`/api/social/loans/${id}/${action}`, { method: 'POST' })
export const loanLabel: Record<Loan['status'], string> = {
  pending: 'En attente',
  accepted: 'Accepté · À remettre',
  active: 'Prêt en cours',
  returned: 'Retourné',
  declined: 'Refusé',
}

export type CollectionAudience = 'personal' | 'friends' | 'followers' | 'app'
export interface CollectionPresentation {
  name: string
  type: string | null
  color: string | null
  photoUrl: string | null
}
export interface CollectionPreview {
  digest: string
  name: string
  cover: string
  items: CollectionPresentation[]
  excludedCount: number
}
export interface PublishedCollection {
  id: string
  name: string
  cover: string
  visibility: CollectionAudience
  owner: string | null
  items: CollectionPresentation[]
}
export const previewCollection = (id: string) =>
  api<CollectionPreview>(`/api/social/collections/${id}/preview`)
export const setCollectionAudience = (
  id: string,
  version: number,
  visibility: CollectionAudience,
  approved: boolean,
  digest?: string,
) =>
  api<{ visibility: CollectionAudience; version: number }>(
    `/api/social/collections/${id}/audience`,
    {
      method: 'PUT',
      body: { version, visibility, approved, digest },
    },
  )
export const discoverCollections = (page = 1) =>
  api<{ collections: PublishedCollection[]; page: number; hasMore: boolean }>(
    `/api/social/collection-publications?page=${page}`,
  )
export const getPublishedCollection = (id: string) =>
  api<{ collection: PublishedCollection }>(`/api/social/collection-publications/${id}`)
