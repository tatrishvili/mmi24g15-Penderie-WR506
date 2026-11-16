import { itemText as t } from '../lib/itemCatalog.ts'
import { t as translate } from '../i18n/copy.ts'
import RoomAccessPage from './RoomAccessPage.tsx'
import FamilyAccountsPage from './FamilyAccountsPage.tsx'
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
  const [housingType, setHousingType] = useState(() => t('Maison', 'House'))
  const [query, setQuery] = useState('')
  const [roomId, setRoomId] = useState(search.get('room') ?? '')
  const [kind, setKind] = useState(() => t('Armoire', 'Wardrobe'))
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
        {busy ? t('Enregistrement…', 'Saving…') : label}
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
      <DesignPage frame="160:152" household title={t('Mon foyer', 'My household')} subtitle={t('Profil enfant', 'Child profile')}>
        <DesignNotice>
          {t('La gestion du foyer est réservée aux adultes. Retrouvez vos articles dans votre dressing.', 'Household management is for adults. Find your items in your wardrobe.')}
        </DesignNotice>
        <Link className="btn btn-primary" to="/dressing">
          {t('Mon dressing', 'My wardrobe')}
        </Link>
      </DesignPage>
    )

  if (step === 'create')
    return (
      <DesignPage
        frame="160:8"
        household
        className="household-create"
        title={t('Créer mon foyer', 'Create my household')}
        subtitle={t('Commençons par organiser votre maison.', 'Let’s start by organizing your home.')}
        footer={
          <>
            {submit(t('Créer mon foyer', 'Create my household'), 'household-create')}
            <Link className="design-link" to="/dressing">
              {translate('verify.setupLater')}
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
          <DesignField label={t('Nom de votre foyer', 'Your household name')}>
            <input
              required
              maxLength={100}
              placeholder={t('Ex. Maison Martin', 'E.g. The Martin house')}
              value={name}
              onChange={(e) => setName(e.target.value)}
            />
          </DesignField>
          <fieldset className="design-options">
            <legend>{t('Type de logement (facultatif)', 'Housing type (optional)')}</legend>
            <div className="design-chips">
              {[t('Maison', 'House'), t('Appartement', 'Flat')].map((t) => (
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
          <p className="design-hint">{t('Vous pourrez ajouter d’autres foyers plus tard.', 'You can add more households later.')}</p>
        </DesignForm>
      </DesignPage>
    )

  if (step === 'members')
    return (
      <DesignPage
        frame="160:27"
        household
        className="household-members"
        title={t('Qui habite ici ?', 'Who lives here?')}
        subtitle={t('Invitez vos proches ou créez leurs profils.', 'Invite your loved ones or create their profiles.')}
        footer={
          <>
            <Link className="btn btn-primary" to="/foyer/pieces/ajouter">
              {t('Continuer', 'Continue')}
            </Link>
            <Link className="design-link" to="/foyer">
              {t('Je le ferai plus tard', 'I will do it later')}
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
                p.role === 'child'
                  ? t('Profil enfant géré par un adulte', 'Child profile managed by an adult')
                  : t('Administrateur du foyer', 'Household administrator')
              }
            />
          ))}
          <DesignRow
            icon="+"
            title={t('Ajouter un adulte', 'Add an adult')}
            detail={t('Invitation à rejoindre le foyer', 'Invitation to join the household')}
            onClick={() => setInvite(!invite)}
          />
          {invite && (
            <DesignNotice>
              {t('Les invitations sécurisées par e-mail ne sont pas encore disponibles. Aucun compte ni invitation n’a été créé.', 'Secure e-mail invitations are not available yet. No account or invitation was created.')}
            </DesignNotice>
          )}
          <Link className="btn btn-secondary" to="/foyer/enfants">
            {t('Comptes enfants et parents responsables', 'Child accounts and responsible parents')}
          </Link>
          <DesignRow
            icon="+"
            title={t('Ajouter un enfant', 'Add a child')}
            detail={t('Profil géré ou compte avec accès', 'Managed profile or account with access')}
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
          {t('Gérer les profils et accès existants', 'Manage existing profiles and access')}
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
        title={t('Mes pièces et rangements', 'My rooms and storage')}
        subtitle={t('Ajoutez vos armoires, cartons ou tiroirs.', 'Add your wardrobes, boxes or drawers.')}
        footer={
          <Link className="btn btn-primary" to="/foyer">
            {t('Terminer', 'Finish')}
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
                  : t('Ajouter un rangement +', 'Add storage +')
              }
              to={`/foyer/rangement/ajouter?room=${r.id}`}
            />
          ))}
        </div>
        {!loading && allRooms.length === 0 && (
          <Link className="design-link" to="/foyer/pieces/ajouter">
            {t('Ajouter les pièces', 'Add rooms')}
          </Link>
        )}
        <p className="design-hint">
          {t('Les rangements peuvent être créés plus tard, au moment de scanner un article.', 'Storage can be created later, when scanning an item.')}
        </p>
      </DesignPage>
    )

  if (step === 'add-storage')
    return (
      <DesignPage
        frame="160:189"
        household
        className="household-add-storage"
        title={t('Ajouter un rangement', 'Add storage')}
        subtitle={t('Où rangez-vous vos objets ?', 'Where do you store your belongings?')}
        footer={submit(t('Créer le rangement', 'Create storage'), 'storage-form')}
      >
        <ErrorMessage error={placeError} />
        <DesignForm
          id="storage-form"
          onSubmit={(e) => {
            e.preventDefault()
            void run(async () => {
              if (!room) throw new Error(t('Ajoutez une pièce avant de créer un rangement.', 'Add a room before creating storage.'))
              await createStorageLocation(room.id, name, parentId ? Number(parentId) : null)
              navigate('/foyer/rangements')
            })
          }}
        >
          <DesignField label={t('Pièce', 'Room')}>
            <select
              required
              value={room?.id ?? ''}
              onChange={(e) => {
                setRoomId(e.target.value)
                setParentId('')
              }}
            >
              <option value="" disabled>
                {t('Choisir une pièce', 'Choose a room')}
              </option>
              {allRooms.map((r) => (
                <option key={r.id} value={r.id}>
                  {r.placeName} · {r.name}
                </option>
              ))}
            </select>
          </DesignField>
          <DesignField label={t('Type de rangement', 'Storage type')}>
            <select value={kind} onChange={(e) => setKind(e.target.value)}>
              {[t('Armoire', 'Wardrobe'), t('Tiroir', 'Drawer'), t('Étagère', 'Shelf'), t('Carton', 'Cardboard box'), t('Valise', 'Suitcase'), t('Boîte', 'Box')].map((k) => (
                <option key={k}>{k}</option>
              ))}
            </select>
          </DesignField>
          <DesignField label={t('Nom', 'Name')}>
            <input
              required
              maxLength={100}
              placeholder={`${t('Ex.', 'E.g.')} ${kind} ${t('principale', 'main')}`}
              value={name}
              onChange={(e) => setName(e.target.value)}
            />
          </DesignField>
          {room && room.storageLocations.length > 0 && (
            <DesignField label={t('Dans un rangement existant (facultatif)', 'Inside existing storage (optional)')}>
              <select value={parentId} onChange={(e) => setParentId(e.target.value)}>
                <option value="">{t('Directement dans la pièce', 'Directly in the room')}</option>
                {room.storageLocations.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </select>
            </DesignField>
          )}
          <fieldset className="design-options">
            <legend>{t('Rangements courants', 'Common storage')}</legend>
            <div className="design-chips design-chips--three">
              {[t('Armoire', 'Wardrobe'), t('Tiroir', 'Drawer'), t('Étagère', 'Shelf'), t('Carton', 'Cardboard box'), t('Valise', 'Suitcase'), t('Boîte', 'Box')].map((k) => (
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
          <span className="sr-only">{t('Choisir un foyer', 'Choose a household')}</span>
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
      subtitle={t('Mon foyer', 'My household')}
      footer={
        <Link className="btn btn-primary" to="/scanner">
          <FigmaGlyph name="scanButton" /> {t('Scanner un article', 'Scan an item')}
        </Link>
      }
    >
      <ErrorMessage error={placeError ?? error} />
      <div className="design-search-wrap">
        <FigmaGlyph name="homeSearch" />
        <input
          className="design-search"
          type="search"
          aria-label={t('Rechercher une pièce', 'Search for a room')}
          placeholder={t('Rechercher un objet ou une pièce…', 'Search for an item or room…')}
          value={query}
          onChange={(e) => setQuery(e.target.value)}
        />
      </div>
      <h2>{t('Pièces du foyer', 'Household rooms')}</h2>
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
          {t('Ajouter les pièces', 'Add rooms')}
        </Link>
      )}
      <h2>{t('Membres du foyer', 'Household members')}</h2>
      <DesignRow
        icon="●"
        title={auth.profiles.map((p) => p.name).join(' · ')}
        detail={t('Ajouter ou gérer un membre ›', 'Add or manage a member ›')}
        to="/foyer/membres"
      />
      <div className="design-inline-links">
        <Link to="/foyer/pieces/ajouter">{t('Ajouter une pièce', 'Add a room')}</Link>
        <Link to="/foyer/rangements">{t('Mes rangements', 'My storage')}</Link>
        <Link to="/foyer/creer">{t('Créer un foyer', 'Create a household')}</Link>
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
