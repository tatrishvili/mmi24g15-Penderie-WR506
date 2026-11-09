import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Home, Layers3, Plus, Warehouse } from 'lucide-react'
import { ApiError } from '../api/client.ts'
import { createPlace, createRoom, createStorageLocation, listPlaces } from '../api/places.ts'
import { t } from 'i18next'
import type { Place, Room, StorageLocation } from '../types/location.ts'
import { flattenStorageLocations } from '../types/location.ts'

function errorMessage(error: unknown): string {
  return error instanceof ApiError ? error.message : t('unexpectedError')
}

function StorageTree({ entries, depth = 0 }: { entries: StorageLocation[]; depth?: number }) {
  if (entries.length === 0) return null

  return (
    <ul className="location-tree">
      {entries.map((entry) => (
        <li key={entry.id} style={{ marginLeft: depth * 14 }}>
          <span><Warehouse size={15} aria-hidden="true" /> {entry.name}</span>
          <StorageTree entries={entry.children} depth={depth + 1} />
        </li>
      ))}
    </ul>
  )
}

function RoomCard({ room, onCreated }: { room: Room; onCreated: () => Promise<void> }) {
  const [name, setName] = useState('')
  const [parentId, setParentId] = useState('')
  const [busy, setBusy] = useState(false)
  const storageOptions = flattenStorageLocations(room.storageLocations)

  async function submit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await createStorageLocation(room.id, name.trim(), parentId === '' ? null : Number(parentId))
      setName('')
      setParentId('')
      await onCreated()
    } catch (cause) {
      setError(errorMessage(cause))
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="room-card">
      <h3>{room.name}</h3>
      {room.storageLocations.length > 0
        ? <StorageTree entries={room.storageLocations} />
        : <p className="auth-sub">Aucun rangement pour le moment.</p>}
      <form className="location-add-form" onSubmit={(event) => void submit(event)}>
        <label className="sr-only" htmlFor={`storage-name-${room.id}`}>Nom du rangement ou de la boîte</label>
        <input
          id={`storage-name-${room.id}`}
          className="input"
          value={name}
          onChange={(event) => setName(event.target.value)}
          placeholder="Rangement ou boîte"
          required
        />
        {storageOptions.length > 0 && (
          <div className="field">
            <label htmlFor={`storage-parent-${room.id}`}>Dans un rangement existant (optionnel)</label>
            <select
              id={`storage-parent-${room.id}`}
              className="input"
              value={parentId}
              onChange={(event) => setParentId(event.target.value)}
            >
              <option value="">À la racine de la pièce</option>
              {storageOptions.map((option) => (
                <option key={option.id} value={option.id}>{option.label}</option>
              ))}
            </select>
          </div>
        )}
        {error !== null && <p role="alert" className="form-error">{error}</p>}
        <button type="submit" className="btn btn-secondary" disabled={busy}>
          <Plus size={16} aria-hidden="true" /> Ajouter un rangement / une boîte
        </button>
      </form>
    </section>
  )
}

function PlaceCard({ place, onCreated }: { place: Place; onCreated: () => Promise<void> }) {
  const [roomPreset, setRoomPreset] = useState('')
  const [customRoomName, setCustomRoomName] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function submit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const roomName = roomPreset === 'custom' ? customRoomName.trim() : roomPreset
      await createRoom(place.id, roomName)
      setRoomPreset('')
      setCustomRoomName('')
      await onCreated()
    } catch (cause) {
      setError(errorMessage(cause))
    } finally {
      setBusy(false)
    }
  }

  return (
    <article className="place-card">
      <h2><Home size={20} aria-hidden="true" /> {place.name}</h2>
      {place.rooms.map((room) => (
        <RoomCard key={room.id} room={room} onCreated={onCreated} />
      ))}
      <form className="location-add-form" onSubmit={(event) => void submit(event)}>
        <label htmlFor={`room-name-${place.id}`}>{t('rooms.addRoom')}</label>
        <select
          id={`room-name-${place.id}`}
          className="input"
          value={roomPreset}
          onChange={(event) => setRoomPreset(event.target.value)}
          required
        >
          <option value="" disabled>{t('rooms.chooseRoom')}</option>
          {storageOptions.map((option) => (
            <option key={option.id} value={option.id}>{option.label}</option>
          ))}
          <option value="custom">{t('rooms.customOption')}</option>
        </select>
        {roomPreset === 'custom' && (
          <input
            className="input"
            aria-label={t('rooms.customNameLabel')}
            value={customRoomName}
            onChange={(event) => setCustomRoomName(event.target.value)}
            placeholder={t('rooms.customNamePlaceholder')}
            maxLength={120}
            required
          />
        )}
        {error !== null && <p role="alert" className="form-error">{error}</p>}
        <button type="submit" className="btn btn-secondary" disabled={busy}>
          <Plus size={16} aria-hidden="true" /> Ajouter une pièce
        </button>
      </form>
    </article>
  )
}

export default function PlacesPage() {
  const [places, setPlaces] = useState<Place[]>([])
  const [name, setName] = useState('')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    try {
      setPlaces((await listPlaces()).places)
      setError(null)
    } catch (cause) {
      setError(errorMessage(cause))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    let cancelled = false
    void listPlaces()
      .then(({ places: loadedPlaces }) => {
        if (!cancelled) {
          setPlaces(loadedPlaces)
          setError(null)
        }
      })
      .catch((cause: unknown) => {
        if (!cancelled) setError(errorMessage(cause))
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [])

  async function submit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await createPlace(name.trim())
      setName('')
      await load()
    } catch (cause) {
      setError(errorMessage(cause))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="auth-page">
      <div className="auth-card">
        <p className="auth-tagline">Organisez vos affaires par lieu de rangement.</p>
        <h1>Maisons et rangements</h1>
        <p className="auth-sub">
          Maison → pièce → rangement → étagère ou boîte. Choisissez ensuite l’emplacement exact de chaque article.
        </p>
        {error !== null && <p role="alert" className="form-error">{error}</p>}
        <form className="location-add-form" onSubmit={(event) => void submit(event)}>
          <label htmlFor="place-name">Ajouter une maison ou un lieu</label>
          <input
            id="place-name"
            className="input"
            value={name}
            onChange={(event) => setName(event.target.value)}
            placeholder="Ex. Maison, appartement de vacances"
            required
          />
          <button type="submit" className="btn btn-primary" disabled={busy}>
            <Plus size={17} aria-hidden="true" /> Ajouter un lieu
          </button>
        </form>
        {loading ? (
          <p className="auth-sub">Chargement…</p>
        ) : places.length === 0 ? (
          <p className="auth-sub">Aucun lieu enregistré.</p>
        ) : (
          <div className="places-list">
            {places.map((place) => <PlaceCard key={place.id} place={place} onCreated={load} />)}
          </div>
        )}
        <p className="location-path"><Layers3 size={15} aria-hidden="true" /> Maison → pièce → rangement → boîte → article</p>
      </div>
    </div>
  )
}