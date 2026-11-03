import { itemText as t } from '../lib/itemCatalog.ts'
import RoomAccessPage from './RoomAccessPage.tsx'
import FamilyAccountsPage from './FamilyAccountsPage.tsx'
import { getCopy } from '../i18n/copy.ts'
import FigmaGlyph from '../components/FigmaGlyph.tsx'
import houseSymbol from '../images/figma/house-symbol.svg'
import { errorText } from '../lib/errors.ts'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useAuth } from '../auth/useAuth.ts'
import { createStorageLocation, listPlaces } from '../api/places.ts'
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
  const [roomId, setRoomId] = useState(search.get('room') ?? '')
  const [kind, setKind] = useState('Armoire')
  const [parentId, setParentId] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [invite, setInvite] = useState(false)
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
          <Link className="btn btn-secondary" to="/foyer/enfants">
            {t('Comptes enfants et parents responsables', 'Child accounts and responsible parents')}
          </Link>
          <DesignRow
            icon="+"
            title="Ajouter un enfant"
            detail="Profil géré ou compte avec accès"
            to="/foyer/enfant"
          />
        </div>
        <p className="design-hint">
          {t(
            'Plusieurs chambres ou salles de bain sont possibles. Chaque objet garde sa propre visibilité.',
            'Multiple bedrooms and bathrooms are supported. Each belonging keeps its own visibility.',
          )}
        </p>
        <Link className="design-link" to="/profils">
          Gérer les profils et accès existants
        </Link>
      </DesignPage>
    )

  if (step === 'child') return <FamilyAccountsPage />

  if (step === 'rooms') return <RoomAccessPage />

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
