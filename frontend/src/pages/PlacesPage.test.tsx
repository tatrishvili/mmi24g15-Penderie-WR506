import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import PlacesPage from './PlacesPage.tsx'
import type { Place, Room, StorageLocation } from '../types/location.ts'
import { jsonBody, stubFetch } from '../test/http.ts'

describe('PlacesPage', () => {
  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
  })

  it('creates a place, room, storage unit, and nested box', async () => {
    let places: Place[] = []
    let nextId = 0

    stubFetch((url, init) => {
      if (url.endsWith('/api/places') && init?.method === 'POST') {
        const place: Place = {
          id: ++nextId,
          name: String(jsonBody(init).name),
          isDefault: false,
          rooms: [],
        }
        places = [...places, place]
        return { status: 201, body: { place } }
      }

      const roomMatch = url.match(/\/api\/places\/(\d+)\/rooms$/)
      if (roomMatch !== null && init?.method === 'POST') {
        const placeId = Number(roomMatch[1])
        const room: Room = {
          id: ++nextId,
          name: String(jsonBody(init).name),
          storageLocations: [],
        }
        places = places.map((place) => place.id === placeId
          ? { ...place, rooms: [...place.rooms, room] }
          : place)
        return { status: 201, body: { room } }
      }

      const storageMatch = url.match(/\/api\/rooms\/(\d+)\/storage-locations$/)
      if (storageMatch !== null && init?.method === 'POST') {
        const roomId = Number(storageMatch[1])
        const data = jsonBody(init)
        let storage: StorageLocation = {
          id: ++nextId,
          name: String(data.name),
          parentId: typeof data.parentId === 'number' ? data.parentId : null,
          children: [],
        }
        const addStorage = (entries: StorageLocation[]): StorageLocation[] => {
          if (storage.parentId === null) return [...entries, storage]

          return entries.map((entry) => entry.id === storage.parentId
            ? { ...entry, children: [...entry.children, storage] }
            : { ...entry, children: addStorage(entry.children) })
        }
        places = places.map((place) => ({
          ...place,
          rooms: place.rooms.map((room) => room.id === roomId
            ? { ...room, storageLocations: addStorage(room.storageLocations) }
            : room),
        }))
        return { status: 201, body: { storageLocation: storage } }
      }

      if (url.endsWith('/api/places')) return { status: 200, body: { places } }
      return { status: 404, body: {} }
    })

    render(
      <MemoryRouter>
        <PlacesPage />
      </MemoryRouter>,
    )

    fireEvent.change(await screen.findByLabelText('Ajouter une maison ou un lieu'), {
      target: { value: 'Maison' },
    })
    fireEvent.click(screen.getByRole('button', { name: /Ajouter un lieu/ }))

    fireEvent.change(await screen.findByLabelText('Ajouter une pièce'), {
      target: { value: 'Chambre' },
    })
    fireEvent.click(screen.getByRole('button', { name: /Ajouter une pièce/ }))

    const storageName = await screen.findByLabelText('Nom du rangement ou de la boîte')
    fireEvent.change(storageName, { target: { value: 'Penderie' } })
    fireEvent.click(screen.getByRole('button', { name: /Ajouter un rangement \/ une boîte/ }))

    const parentSelect = await screen.findByLabelText('Dans un rangement existant (optionnel)')
    fireEvent.change(parentSelect, { target: { value: String(nextId) } })
    fireEvent.change(screen.getByLabelText('Nom du rangement ou de la boîte'), {
      target: { value: 'Boîte hiver' },
    })
    fireEvent.click(screen.getByRole('button', { name: /Ajouter un rangement \/ une boîte/ }))

    expect(await screen.findByText('Boîte hiver')).toBeInTheDocument()
  })
})
