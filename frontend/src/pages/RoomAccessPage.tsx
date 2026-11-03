import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { createRoom, listPlaces, updateRoom } from '../api/places.ts'
import { useAuth } from '../auth/useAuth.ts'
import { DesignPage, DesignField, DesignForm, ErrorMessage } from '../components/DesignPage.tsx'
import { errorText } from '../lib/errors.ts'
import { itemText as t } from '../lib/itemCatalog.ts'
import type { Place, Room } from '../types/location.ts'

const kinds = () => [
  ['bedroom', t('Chambre', 'Bedroom')],
  ['bathroom', t('Salle de bain', 'Bathroom')],
  ['garage', t('Garage', 'Garage')],
  ['living', t('Salon', 'Living room')],
  ['kitchen', t('Cuisine', 'Kitchen')],
  ['office', t('Bureau', 'Office')],
  ['storage', t('Rangement / cave', 'Storage / cellar')],
  ['other', t('Autre', 'Other')],
]
export default function RoomAccessPage() {
  const { profiles, activeProfile } = useAuth()
  const [places, setPlaces] = useState<Place[]>([])
  const [placeId, setPlaceId] = useState(0)
  const [editing, setEditing] = useState<number | null>(null)
  const [name, setName] = useState('')
  const [kind, setKind] = useState('bedroom')
  const [common, setCommon] = useState(false)
  const [assigned, setAssigned] = useState<number[]>([])
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)
  const [loading, setLoading] = useState(true)
  const load = async () => {
    const data = await listPlaces()
    setPlaces(data.places)
    return data.places
  }
  useEffect(() => {
    let live = true
    listPlaces()
      .then((data) => {
        if (live) {
          setPlaces(data.places)
          setPlaceId(data.places[0]?.id ?? 0)
        }
      })
      .catch((e) => {
        if (live) setError(errorText(e))
      })
      .finally(() => {
        if (live) setLoading(false)
      })
    return () => {
      live = false
    }
  }, [])
  function edit(room: Room, place: Place) {
    setEditing(room.id)
    setName(room.name)
    setKind(room.kind ?? 'other')
    setCommon(room.common ?? false)
    setAssigned(room.assignedProfileIds ?? [])
    setPlaceId(place.id)
    setNotice('')
  }
  const reset = () => {
    setEditing(null)
    setName('')
    setAssigned([])
    setCommon(false)
  }
  if (activeProfile?.role === 'child')
    return <p>{t('Les pièces sont gérées par tes parents.', 'Your parents manage rooms.')}</p>
  return (
    <DesignPage
      frame="160:81"
      household
      title={t('Pièces du foyer', 'Household rooms')}
      subtitle={t(
        'Trois chambres ? Ajoutez trois pièces, chacune avec son nom.',
        'Three bedrooms? Add three rooms, each with its own name.',
      )}
      footer={
        <Link className="btn btn-primary" to="/foyer/rangements">
          {t('Continuer vers les rangements', 'Continue to storage')}
        </Link>
      }
    >
      <ErrorMessage error={error} />
      {notice && <p role="status">{notice}</p>}
      <DesignForm
        id="room-access-form"
        onSubmit={(event) => {
          event.preventDefault()
          if (busy) return
          setBusy(true)
          setError(null)
          setNotice('')
          const body = { name: name.trim(), kind, common, assignedProfileIds: assigned }
          void (editing ? updateRoom(editing, body) : createRoom(placeId, body.name, body))
            .then(async () => {
              await load()
              reset()
              setNotice(t('Pièce enregistrée.', 'Room saved.'))
            })
            .catch((e) => setError(errorText(e)))
            .finally(() => setBusy(false))
        }}
      >
        <h2>
          {editing
            ? t('Modifier cette pièce', 'Edit this room')
            : t('Ajouter une pièce', 'Add a room')}
        </h2>
        <DesignField label={t('Lieu', 'Place')}>
          <select
            value={placeId}
            disabled={editing !== null}
            onChange={(e) => setPlaceId(Number(e.target.value))}
            required
          >
            <option value={0} disabled>
              {t('Choisir un lieu', 'Choose a place')}
            </option>
            {places.map((place) => (
              <option key={place.id} value={place.id}>
                {place.name}
              </option>
            ))}
          </select>
        </DesignField>
        <DesignField label={t('Type de pièce', 'Room type')}>
          <select value={kind} onChange={(e) => setKind(e.target.value)}>
            {kinds().map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </DesignField>
        <DesignField label={t('Nom de cette pièce', 'Name of this room')}>
          <input
            required
            maxLength={120}
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder={t(
              'Ex. Chambre d’Emma, salle de bain du haut…',
              'E.g. Emma’s bedroom, upstairs bathroom…',
            )}
          />
        </DesignField>
        <label className="family-choice">
          <input type="checkbox" checked={common} onChange={(e) => setCommon(e.target.checked)} />
          {t(
            'Pièce commune : tous les membres peuvent y ranger leurs objets',
            'Common room: everyone may store their belongings here',
          )}
        </label>
        {!common && (
          <fieldset>
            <legend>{t('Qui utilise cette pièce ?', 'Who uses this room?')}</legend>
            {profiles.map((profile) => (
              <label className="family-choice" key={profile.id}>
                <input
                  type="checkbox"
                  checked={assigned.includes(profile.id)}
                  onChange={(e) =>
                    setAssigned((ids) =>
                      e.target.checked
                        ? [...ids, profile.id]
                        : ids.filter((id) => id !== profile.id),
                    )
                  }
                />
                {profile.name}
              </label>
            ))}
          </fieldset>
        )}
        <p className="design-hint">
          {t(
            'Partager une chambre ne rend pas les objets visibles. La visibilité se choisit sur chaque objet.',
            'Sharing a bedroom does not make belongings visible. Choose visibility on each object.',
          )}
        </p>
        <button className="btn btn-primary" disabled={busy || loading || !placeId}>
          {busy
            ? t('Enregistrement…', 'Saving…')
            : editing
              ? t('Enregistrer les changements', 'Save changes')
              : t('Ajouter cette pièce', 'Add this room')}
        </button>
        {editing && (
          <button className="btn btn-secondary" type="button" onClick={reset}>
            {t('Annuler la modification', 'Cancel editing')}
          </button>
        )}
      </DesignForm>
      {places.map((place) => (
        <section key={place.id} className="family-section">
          <h2>{place.name}</h2>
          {place.rooms.map((room) => (
            <div className="family-room-row" key={room.id}>
              <div>
                <strong>{room.name}</strong>
                <p>
                  {room.common
                    ? t('Pièce commune', 'Common room')
                    : (room.assignedProfileIds ?? [])
                        .map((id) => profiles.find((profile) => profile.id === id)?.name)
                        .filter(Boolean)
                        .join(', ') || t('Accès enfant à attribuer', 'Assign child access')}
                </p>
              </div>
              <button
                className="btn btn-secondary btn-inline"
                type="button"
                onClick={() => edit(room, place)}
              >
                {t('Modifier', 'Edit')}
              </button>
            </div>
          ))}
        </section>
      ))}
      {!loading && places.length === 0 && (
        <Link to="/lieux">{t('Créer un lieu dans Lieux', 'Create a place in Places')}</Link>
      )}
    </DesignPage>
  )
}
