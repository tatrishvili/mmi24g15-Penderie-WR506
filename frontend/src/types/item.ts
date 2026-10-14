import type { ProfileSummary } from './auth.ts'

export type ItemCondition = 'neuf' | 'comme_neuf' | 'tres_bon' | 'bon' | 'a_reparer' | 'abime'

export type ItemStatus = 'disponible' | 'loue' | 'vendu' | 'donne' | 'archive'

export interface ItemPhoto {
  id: number
  url: string
  isPrimary: boolean
}

export interface ItemSummary {
  id: number
  name: string
  type: string | null
  category: string | null
  brand: string | null
  color: string | null
  size: string | null
  material: string | null
  condition: ItemCondition
  status: ItemStatus
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
  name: string
  type?: string
  category?: string
  roomId?: number | null
  storageLocationId?: number | null
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

const CONDITION_LABELS: Record<ItemCondition, string> = {
  neuf: 'Neuf',
  comme_neuf: 'Comme neuf',
  tres_bon: 'Très bon état',
  bon: 'Bon état',
  a_reparer: 'À réparer',
  abime: 'Abîmé',
}

const STATUS_LABELS: Record<ItemStatus, string> = {
  disponible: 'Disponible',
  loue: 'Prêté',
  vendu: 'Vendu',
  donne: 'Donné',
  archive: 'Archivé',
}

export function conditionLabel(condition: ItemCondition): string {
  return CONDITION_LABELS[condition]
}

export function statusLabel(status: ItemStatus): string {
  return STATUS_LABELS[status]
}
