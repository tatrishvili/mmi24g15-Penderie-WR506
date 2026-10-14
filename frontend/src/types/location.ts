export interface StorageLocation {
  id: number
  name: string
  parentId: number | null
  children: StorageLocation[]
}

export interface Room {
  id: number
  name: string
  storageLocations: StorageLocation[]
}

export interface Place {
  id: number
  name: string
  isDefault: boolean
  rooms: Room[]
}

export interface PlaceListResult {
  places: Place[]
}

export function flattenStorageLocations(
  locations: StorageLocation[],
  prefix = '',
): { id: number; label: string }[] {
  return locations.flatMap((location) => {
    const label = prefix === '' ? location.name : `${prefix} › ${location.name}`
    return [
      { id: location.id, label },
      ...flattenStorageLocations(location.children, label),
    ]
  })
}
