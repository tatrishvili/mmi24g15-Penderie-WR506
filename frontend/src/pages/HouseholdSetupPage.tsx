import { getCopy } from '../i18n/copy.ts'
import FigmaGlyph from '../components/FigmaGlyph.tsx'
import houseSymbol from '../images/figma/house-symbol.svg'
import { errorText } from '../lib/errors.ts'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useAuth } from '../auth/useAuth.ts'
import { createRoom, createStorageLocation, listPlaces } from '../api/places.ts'
import type { Place } from '../types/location.ts'
import {
  DesignPage,
  DesignRow,
  DesignField,
  DesignForm,
  DesignTile,
  DesignNotice,
  ErrorMessage,
  FrameIcon,
} from '../components/DesignPage.tsx'

const rooms = [
  ['Salon', 'imgIconePieceSalon'],
  ['Chambre', 'imgIconePieceChambre'],
  ['Cuisine', 'imgIconePieceCuisine'],
  ['Dressing', 'imgIconePieceDressing'],
  ['Bureau', 'imgIconePieceBureau'],
  ['Salle de bain', 'imgBathroom'],
  ['Garage', 'imgIconePieceGarage'],
  ['Buanderie', 'imgBuanderie'],
  ['Cave', 'imgStorageRoom'],
] as const
const roomAsset = (name: string) =>
  rooms.find(([label]) => name.toLowerCase().includes(label.toLowerCase()))?.[1]

function usePlaces() {
  const [places, setPlaces] = useState<Place[]>([])
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const { activeHouseholdId } = useAuth()
  useEffect(() => {
    let live = true
    listPlaces()
      .then((data) => {
        if (live) {
          setPlaces(data.places)
          setError(null)
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
  }, [activeHouseholdId])
  return { places, setPlaces, error, loading }
}

export default function HouseholdSetupPage({
  step,
}: {
  step: 'create' | 'members' | 'child' | 'rooms' | 'storage' | 'overview' | 'add-storage'
}) {
  const auth = useAuth()
  const navigate = useNavigate()
  const [search] = useSearchParams()
  const { places, error: placeError, loading } = usePlaces()
  const [name, setName] = useState('')
  const [housingType, setHousingType] = useState('Maison')
  const [query, setQuery] = useState('')
  const [selected, setSelected] = useState<string[]>([])
  const [placeId, setPlaceId] = useState(search.get('place') ?? '')
  const [roomId, setRoomId] = useState(search.get('room') ?? '')
  const [kind, setKind] = useState('Armoire')
  const [parentId, setParentId] = useState('')
  const [pin, setPin] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [invite, setInvite] = useState(false)
  const place = places.find((p) => String(p.id) === placeId) ?? places[0]
  const allRooms = places.flatMap((p) =>
    p.rooms.map((r) => ({ ...r, placeName: p.name, placeId: p.id })),
  )
  const room = allRooms.find((r) => String(r.id) === roomId) ?? allRooms[0]
  const run = async (action: () => Promise<void>) => {
    if (busy) return
    setBusy(true)
    setError(null)
    try {
      await action()
    } catch (e) {
      setError(errorText(e))
    } finally {
      setBusy(false)
    }
  }
  const feedback = <ErrorMessage error={error} />
  const submit = (label: string, form: string) => (
    <>
      <button className="btn btn-primary" form={form} type="submit" disabled={busy || loading}>
        {busy ? 'Enregistrement…' : label}
      </button>
      {feedback}
    </>
  )
  const roomIcon = (name: string, frame: string) => {
    const asset = roomAsset(name)
    return asset ? (
      <FrameIcon frame={frameAssetsForRoom(frame, asset)} name={asset} />
    ) : (
      <span aria-hidden="true">⌂</span>
    )
  }

  if (auth.activeProfile?.role === 'child')
    return (
      <DesignPage frame="160:152" household title="Mon foyer" subtitle="Profil enfant">
        <DesignNotice>
          La gestion du foyer est réservée aux adultes. Retrouvez vos articles dans votre dressing.
        </DesignNotice>
        <Link className="btn btn-primary" to="/dressing">
          Mon dressing
        </Link>
      </DesignPage>
    )

  if (step === 'create')
    return (
      <DesignPage
        frame="160:8"
        household
        className="household-create"
        title="Créer mon foyer"
        subtitle="Commençons par organiser votre maison."
        footer={
          <>
            {submit('Créer mon foyer', 'household-create')}
            <Link className="design-link" to="/dressing">
              {getCopy().verify.setupLater}
            </Link>
          </>
        }
      >
        <div className="household-create__illustration">
          <img src={houseSymbol} alt="" />
        </div>
        <DesignForm
          id="household-create"
          onSubmit={(e) => {
            e.preventDefault()
            void run(async () => {
              await auth.createHousehold({
                householdName: name,
                firstName: auth.activeProfile?.name ?? 'Moi',
                placeName: housingType,
              })
              navigate('/foyer/membres')
            })
          }}
        >
          <DesignField label="Nom de votre foyer">
            <input
              required
              maxLength={100}
              placeholder="Ex. Maison Martin"
              value={name}
              onChange={(e) => setName(e.target.value)}
            />
          </DesignField>
          <fieldset className="design-options">
            <legend>Type de logement (facultatif)</legend>
            <div className="design-chips">
              {['Maison', 'Appartement'].map((t) => (
                <button
                  key={t}
                  type="button"
                  aria-pressed={housingType === t}
                  onClick={() => setHousingType(t)}
                >
                  {t}
                </button>
              ))}
            </div>
          </fieldset>
          <p className="design-hint">Vous pourrez ajouter d’autres foyers plus tard.</p>
        </DesignForm>
      </DesignPage>
    )

  if (step === 'members')
    return (
      <DesignPage
        frame="160:27"
        household
        className="household-members"
        title="Qui habite ici ?"
        subtitle="Invitez vos proches ou créez leurs profils."
        footer={
          <>
            <Link className="btn btn-primary" to="/foyer/pieces/ajouter">
              Continuer
            </Link>
            <Link className="design-link" to="/foyer">
              Je le ferai plus tard
            </Link>
          </>
        }
      >
        <div className="design-stack">
          {auth.profiles.map((p) => (
            <DesignRow
              key={p.id}
              icon={<span aria-hidden="true">●</span>}
              title={p.name}
              detail={
                p.role === 'child' ? 'Profil enfant géré par un adulte' : 'Administrateur du foyer'
              }
            />
          ))}
          <DesignRow
            icon="+"
            title="Ajouter un adulte"
            detail="Invitation à rejoindre le foyer"
            onClick={() => setInvite(!invite)}
          />
          {invite && (
            <DesignNotice>
              Les invitations sécurisées par e-mail ne sont pas encore disponibles. Aucun compte ni
              invitation n’a été créé.
            </DesignNotice>
          )}
          <DesignRow
            icon="+"
            title="Ajouter un enfant"
            detail="Profil géré ou compte avec accès"
            to="/foyer/enfant"
          />
        </div>
        <p className="design-hint">
          Les pièces sont partagées. Les objets peuvent appartenir à des personnes différentes.
        </p>
        <Link className="design-link" to="/profils">
          Gérer les profils et accès existants
        </Link>
      </DesignPage>
    )

  if (step === 'child')
    return (
      <DesignPage
        frame="160:57"
        household
        className="household-child"
        title="Ajouter un enfant"
        subtitle="Choisissez son mode d’accès."
        footer={submit('Ajouter au foyer', 'child-form')}
      >
        <DesignForm
          id="child-form"
          onSubmit={(e) => {
            e.preventDefault()
            void run(async () => {
              await auth.createProfile({ role: 'child', name, pin })
              navigate('/foyer/membres')
            })
          }}
        >
          <DesignField label="Prénom">
            <input
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              maxLength={100}
              placeholder="Ex. Léa"
            />
          </DesignField>
          <div className="household-child__access">
            <h2>Type de compte</h2>
            <DesignRow
              icon="○"
              title="Profil sur cet appareil"
              detail="Accès limité avec un adulte connecté"
            />
            <DesignField label="Code de déverrouillage (4 à 6 chiffres)">
              <input
                inputMode="numeric"
                pattern="[0-9]{4,6}"
                required
                type="password"
                value={pin}
                onChange={(e) => setPin(e.target.value)}
                autoComplete="new-password"
              />
            </DesignField>
            <small>
              La connexion autonome sur l’appareil de l’enfant n’est pas encore disponible.
            </small>
          </div>
        </DesignForm>
      </DesignPage>
    )

  if (step === 'rooms')
    return (
      <DesignPage
        frame="160:81"
        household
        className="household-rooms"
        title="Ajouter les pièces"
        subtitle="Choisissez uniquement les pièces présentes."
        footer={submit(
          `Continuer · ${selected.length} pièce${selected.length > 1 ? 's' : ''}`,
          'rooms-form',
        )}
      >
        <ErrorMessage error={placeError} />
        <DesignForm
          id="rooms-form"
          onSubmit={(e) => {
            e.preventDefault()
            void run(async () => {
              if (!place) throw new Error('Créez d’abord un lieu dans Lieux.')
              for (const label of selected) {
                if (!place.rooms.some((r) => r.name.toLowerCase() === label.toLowerCase())) {
                  await createRoom(place.id, label)
                  setSelected((v) => v.filter((s) => s !== label))
                }
              }
              navigate('/foyer/rangements')
            })
          }}
        >
          {places.length > 1 && (
            <DesignField label="Lieu">
              <select
                value={place?.id ?? ''}
                onChange={(e) => {
                  setPlaceId(e.target.value)
                  setSelected([])
                }}
              >
                {places.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </select>
            </DesignField>
          )}
          <div className="design-search-wrap">
            <FigmaGlyph name="roomSearch" />
            <input
              className="design-search"
              aria-label="Rechercher une pièce"
              type="search"
              placeholder="Rechercher une pièce…"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
            />
          </div>
          <div className="household-room-grid">
            {rooms
              .filter(([label]) => label.toLowerCase().includes(query.toLowerCase()))
              .map(([label, asset]) => (
                <button
                  type="button"
                  key={label}
                  className={selected.includes(label) ? 'is-selected' : ''}
                  aria-pressed={selected.includes(label)}
                  onClick={() =>
                    setSelected((v) =>
                      v.includes(label) ? v.filter((s) => s !== label) : [...v, label],
                    )
                  }
                >
                  <FrameIcon frame="160:81" name={asset} />
                  <strong>{label}</strong>
                  {selected.includes(label) && (
                    <span className="household-room-grid__check" aria-hidden="true">
                      <FigmaGlyph name="roomCheck" />
                    </span>
                  )}
                </button>
              ))}
          </div>
          <Link className="design-link" to="/lieux">
            Voir toutes les pièces ›
          </Link>
        </DesignForm>
      </DesignPage>
    )

  if (step === 'storage')
    return (
      <DesignPage
        frame="160:123"
        household
        className="household-storage"
        title="Mes pièces et rangements"
        subtitle="Ajoutez vos armoires, cartons ou tiroirs."
        footer={
          <Link className="btn btn-primary" to="/foyer">
            Terminer
          </Link>
        }
      >
        <ErrorMessage error={placeError} />
        <div className="design-stack">
          {allRooms.map((r) => (
            <DesignRow
              key={r.id}
              icon={roomIcon(r.name, '160:123')}
              title={r.name}
              detail={
                r.storageLocations.length
                  ? r.storageLocations.map((s) => s.name).join(' · ') + ' +'
                  : 'Ajouter un rangement +'
              }
              to={`/foyer/rangement/ajouter?room=${r.id}`}
            />
          ))}
        </div>
        {!loading && allRooms.length === 0 && (
          <Link className="design-link" to="/foyer/pieces/ajouter">
            Ajouter les pièces
          </Link>
        )}
        <p className="design-hint">
          Les rangements peuvent être créés plus tard, au moment de scanner un article.
        </p>
      </DesignPage>
    )

  if (step === 'add-storage')
    return (
      <DesignPage
        frame="160:189"
        household
        className="household-add-storage"
        title="Ajouter un rangement"
        subtitle="Où rangez-vous vos objets ?"
        footer={submit('Créer le rangement', 'storage-form')}
      >
        <ErrorMessage error={placeError} />
        <DesignForm
          id="storage-form"
          onSubmit={(e) => {
            e.preventDefault()
            void run(async () => {
              if (!room) throw new Error('Ajoutez une pièce avant de créer un rangement.')
              await createStorageLocation(room.id, name, parentId ? Number(parentId) : null)
              navigate('/foyer/rangements')
            })
          }}
        >
          <DesignField label="Pièce">
            <select
              required
              value={room?.id ?? ''}
              onChange={(e) => {
                setRoomId(e.target.value)
                setParentId('')
              }}
            >
              <option value="" disabled>
                Choisir une pièce
              </option>
              {allRooms.map((r) => (
                <option key={r.id} value={r.id}>
                  {r.placeName} · {r.name}
                </option>
              ))}
            </select>
          </DesignField>
          <DesignField label="Type de rangement">
            <select value={kind} onChange={(e) => setKind(e.target.value)}>
              {['Armoire', 'Tiroir', 'Étagère', 'Carton', 'Valise', 'Boîte'].map((k) => (
                <option key={k}>{k}</option>
              ))}
            </select>
          </DesignField>
          <DesignField label="Nom">
            <input
              required
              maxLength={100}
              placeholder={`Ex. ${kind} principale`}
              value={name}
              onChange={(e) => setName(e.target.value)}
            />
          </DesignField>
          {room && room.storageLocations.length > 0 && (
            <DesignField label="Dans un rangement existant (facultatif)">
              <select value={parentId} onChange={(e) => setParentId(e.target.value)}>
                <option value="">Directement dans la pièce</option>
                {room.storageLocations.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </select>
            </DesignField>
          )}
          <fieldset className="design-options">
            <legend>Rangements courants</legend>
            <div className="design-chips design-chips--three">
              {['Armoire', 'Tiroir', 'Étagère', 'Carton', 'Valise', 'Boîte'].map((k) => (
                <button type="button" key={k} aria-pressed={kind === k} onClick={() => setKind(k)}>
                  {k}
                </button>
              ))}
            </div>
          </fieldset>
        </DesignForm>
      </DesignPage>
    )

  return (
    <DesignPage
      frame="160:152"
      household
      className="household-overview"
      title={
        <label className="household-picker">
          <span className="sr-only">Choisir un foyer</span>
          <select
            value={auth.activeHouseholdId ?? ''}
            onChange={(e) => void run(() => auth.selectHousehold(Number(e.target.value)))}
          >
            {auth.households.map((h) => (
              <option key={h.id} value={h.id}>
                {h.name}
              </option>
            ))}
          </select>
        </label>
      }
      subtitle="Mon foyer"
      footer={
        <Link className="btn btn-primary" to="/scanner">
          <FigmaGlyph name="scanButton" /> Scanner un article
        </Link>
      }
    >
      <ErrorMessage error={placeError ?? error} />
      <div className="design-search-wrap">
        <FigmaGlyph name="homeSearch" />
        <input
          className="design-search"
          type="search"
          aria-label="Rechercher une pièce"
          placeholder="Rechercher un objet ou une pièce…"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
        />
      </div>
      <h2>Pièces du foyer</h2>
      <div className="design-grid">
        {allRooms
          .filter((r) => r.name.toLowerCase().includes(query.toLowerCase()))
          .map((r) => (
            <DesignTile
              key={r.id}
              title={r.name}
              detail={r.placeName}
              icon={roomIcon(r.name, '160:152')}
              to={`/dressing?room=${r.id}`}
            />
          ))}
      </div>
      {!loading && allRooms.length === 0 && (
        <Link className="design-link" to="/foyer/pieces/ajouter">
          Ajouter les pièces
        </Link>
      )}
      <h2>Membres du foyer</h2>
      <DesignRow
        icon="●"
        title={auth.profiles.map((p) => p.name).join(' · ')}
        detail="Ajouter ou gérer un membre ›"
        to="/foyer/membres"
      />
      <div className="design-inline-links">
        <Link to="/foyer/pieces/ajouter">Ajouter une pièce</Link>
        <Link to="/foyer/rangements">Mes rangements</Link>
        <Link to="/foyer/creer">Créer un foyer</Link>
      </div>
    </DesignPage>
  )
}

function frameAssetsForRoom(frame: string, asset: string) {
  return [
    'imgIconePieceSalon',
    'imgIconePieceChambre',
    'imgIconePieceCuisine',
    'imgIconePieceDressing',
  ].includes(asset)
    ? frame
    : '160:81'
}
