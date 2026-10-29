import { api } from './client.ts'
import type {
  CreateItemPayload,
  ItemListResult,
  ItemSummary,
  UpdateItemPayload,
} from '../types/item.ts'

export function listItems(params?: {
  q?: string
  status?: string
  page?: number
}): Promise<ItemListResult> {
  const search = new URLSearchParams()
  if (params?.q !== undefined && params.q !== '') search.set('q', params.q)
  if (params?.status !== undefined && params.status !== '') search.set('status', params.status)
  if (params?.page !== undefined) search.set('page', String(params.page))
  const suffix = search.size > 0 ? `?${search.toString()}` : ''
  return api<ItemListResult>(`/api/items${suffix}`)
}

export function createItem(payload: CreateItemPayload): Promise<{ item: ItemSummary }> {
  return api<{ item: ItemSummary }>('/api/items', { method: 'POST', body: payload })
}

export function getItem(id: number): Promise<{ item: ItemSummary }> {
  return api<{ item: ItemSummary }>(`/api/items/${id}`)
}

export function updateItem(id: number, payload: UpdateItemPayload): Promise<{ item: ItemSummary }> {
  return api<{ item: ItemSummary }>(`/api/items/${id}`, { method: 'PATCH', body: payload })
}

export function deleteItem(id: number): Promise<void> {
  return api<void>(`/api/items/${id}`, { method: 'DELETE' })
}

export function getItemByQr(token: string): Promise<{ item: ItemSummary }> {
  return api<{ item: ItemSummary }>(`/api/items/qr/${encodeURIComponent(token)}`)
}
