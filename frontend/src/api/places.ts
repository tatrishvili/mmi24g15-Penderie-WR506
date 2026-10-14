import { api } from './client.ts'
import type { Place, PlaceListResult, Room, StorageLocation } from '../types/location.ts'

export function listPlaces(): Promise<PlaceListResult> {
  return api<PlaceListResult>('/api/places')
}

export function createPlace(name: string): Promise<{ place: Place }> {
  return api<{ place: Place }>('/api/places', { method: 'POST', body: { name } })
}

export function createRoom(placeId: number, name: string): Promise<{ room: Room }> {
  return api<{ room: Room }>(`/api/places/${placeId}/rooms`, {
    method: 'POST',
    body: { name },
  })
}

export function createStorageLocation(
  roomId: number,
  name: string,
  parentId: number | null,
): Promise<{ storageLocation: StorageLocation }> {
  return api<{ storageLocation: StorageLocation }>(`/api/rooms/${roomId}/storage-locations`, {
    method: 'POST',
    body: { name, parentId },
  })
}
