import type { ProfileSummary } from './auth.ts'
import { t } from '../i18n/copy.ts'

export type ItemCondition = 'neuf' | 'comme_neuf' | 'tres_bon' | 'bon' | 'a_reparer' | 'abime'

export type ItemStatus = 'disponible' | 'loue' | 'vendu' | 'donne' | 'archive'

export interface ItemPhoto {
  id: number
  url: string
  isPrimary: boolean
}

export interface ItemSummary {
  canEdit?: boolean
  familyAudience?: string
  familyViewerIds?: number[]
  sharingVersion?: number
  id: number
  name: string
  type: string | null
  category: string | null
  author?: string | null
  artist?: string | null
  publisher?: string | null
  brand: string | null
  color: string | null
  size: string | null
  material: string | null
  condition: ItemCondition
  status: ItemStatus
  visibility?: string
  qrToken: string
  memory: string | null
  notes: string | null
  room: { id: number; name: string; place: { id: number; name: string } } | null
  storageLocation: { id: number; name: string; path: string[] } | null
  ownerProfile: ProfileSummary
  photos: ItemPhoto[]
}

export interface ItemListResult {
  items: ItemSummary[]
  total: number
  page: number
  perPage: number
}

export interface CreateItemPayload {
  ownerProfileId?: number
  name: string
  type?: string
  category?: string
  roomId?: number | null
  storageLocationId?: number | null
  author?: string
  artist?: string
  publisher?: string
  brand?: string
  color?: string
  size?: string
  material?: string
  condition?: ItemCondition
  status?: ItemStatus
  memory?: string
  notes?: string
  photos?: string[]
}

export type UpdateItemPayload = Partial<CreateItemPayload>

export function conditionLabel(condition: ItemCondition): string {
  return t(`items.condition.${condition}`)
}

export function statusLabel(status: ItemStatus): string {
  return t(`items.status.${status}`)
}
