import type { ItemType } from '../lib/itemCatalog.ts'
import { api } from './client'

export interface ScanResult {
  name: string
  type: ItemType
  author?: string
  artist?: string
  publisher?: string
  category: string
  brand: string
  color: string
  size: string
  material: string
  condition: 'neuf' | 'comme_neuf' | 'tres_bon' | 'bon' | 'a_reparer' | 'abime'
  confidence: number
}

export function scan(imageBase64: string, typeHint?: string): Promise<ScanResult> {
  return api<ScanResult>('/api/scan', {
    method: 'POST',
    body: typeHint === undefined ? { image: imageBase64 } : { image: imageBase64, typeHint },
  })
}
