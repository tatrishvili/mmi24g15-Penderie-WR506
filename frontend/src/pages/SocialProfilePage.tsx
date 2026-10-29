import { errorText } from '../lib/errors.ts'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import * as social from '../api/social.ts'
import type { Workspace, Collection } from '../api/social.ts'
import { listAllItems } from '../api/items.ts'
import type { ItemSummary } from '../types/item.ts'
import { resizeToBase64 } from '../lib/image.ts'
import {
  DesignPage,
  DesignRow,
  DesignTile,
  DesignField,
  DesignForm,
  DesignNotice,
  ErrorMessage,
  FrameIcon,
} from '../components/DesignPage.tsx'

function useWorkspace() {
  const [data, setData] = useState<Workspace | null>(null)
  const [error, setError] = useState<string | null>(null)
  useEffect(() => {
    let live = true
    social
      .getWorkspace()
      .then((d) => {
        if (live) setData(d)
      })
      .catch((e) => {
        if (live) setError(errorText(e))
      })
    return () => {
      live = false
    }
  }, [])
  return { data, setData, error, setError }
}

export default function SocialProfilePage({
  view,
}: {
  view: 'profile' | 'edit' | 'collections' | 'create' | 'showcase' | 'collection'
}) {
  const { data, setData, error, setError } = useWorkspace()
  const navigate = useNavigate()
  const { collectionId } = useParams()
  const [busy, setBusy] = useState(false)
  const [name, setName] = useState('')
  const [cover, setCover] = useState('')
  const [profile, setProfile] = useState<Workspace['profile'] | null>(null)
  const [pins, setPins] = useState<string[] | null>(null)
  const [choosePin, setChoosePin] = useState(false)
  const [items, setItems] = useState<ItemSummary[]>([])
  const [notice, setNotice] = useState<string | null>(null)
  useEffect(() => {
    if (view !== 'collection') return
    let live = true
    listAllItems()
      .then((d) => {
        if (live) setItems(d)
      })
      .catch((e) => {
        if (live) setError(errorText(e))
      })
    return () => {
      live = false
    }
  }, [view, setError])
  const frame = {
    profile: '165:8',
    edit: '165:46',
    collections: '165:71',
    create: '165:110',
    showcase: '165:133',
    collection: '165:71',
  }[view]
  const icon = (name: string) => <FrameIcon frame={frame} name={name} />
  const run = async (action: () => Promise<void>) => {
    if (busy) return
    setBusy(true)
    setError(null)
    setNotice(null)
    try {
      await action()
    } catch (e) {
      setError(errorText(e))
    } finally {
      setBusy(false)
    }
  }
  const currentProfile = profile ?? data?.profile ?? { displayName: '', bio: '', photo: '' }
  const currentPins = pins ?? data?.pins ?? []
  const collection = data?.collections.find((c) => c.id === collectionId)
  const collectionIcon = (c: Collection) => {
    if (c.cover) return <img src={c.cover} alt="" />
    const name = c.name.toLowerCase()
    const asset = /look|dressing|mode/.test(name)
      ? 'imgIconShirt'
      : /souvenir/.test(name) && view === 'collections'
        ? 'imgIconHeart'
        : /objet/.test(name) && view === 'collections'
          ? 'imgIconBox'
          : view === 'profile'
            ? 'imgIconDisc1'
            : 'imgIconDisc'
    return icon(asset)
  }
  const tile = (c: Collection, onClick?: () => void) => (
    <DesignTile
      key={c.id}
      title={c.name}
      detail={`${c.itemIds.length} objet${c.itemIds.length > 1 ? 's' : ''} · Privée`}
      icon={collectionIcon(c)}
      to={onClick ? undefined : `/collections/${c.id}`}
      onClick={onClick}
      selected={currentPins.includes(c.id)}
    />
  )
  const errors = (
    <>
      <ErrorMessage error={error} />
      {notice && <p role="status">{notice}</p>}
    </>
  )
  const load = !data && <p role="status">{error ? 'Données indisponibles.' : 'Chargement…'}</p>

  if (view === 'profile')
    return (
      <DesignPage
        frame={frame}
        className="social-profile"
        title="Mon profil"
        subtitle="Votre espace personnel"
        footer={
          <Link className="btn btn-primary" to="/profil/modifier">
            Personnaliser mon profil
          </Link>
        }
      >
        {errors}
        {load}
        {data && (
          <>
            <div className="social-profile__identity">
              <span className="social-avatar">
                {data.profile.photo ? (
                  <img src={data.profile.photo} alt="Votre photo" />
                ) : (
                  icon('imgIconUser')
                )}
                <Link to="/profil/modifier" aria-label="Changer ma photo">
                  {icon('imgIconCamera')}
                </Link>
              </span>
              <div>
                <h2>{data.profile.displayName}</h2>
                <p>@{data.username ?? 'sans_identifiant'}</p>
              </div>
            </div>
            <p className="social-profile__bio">
              {data.profile.bio || 'Ajoutez quelques mots pour vous présenter.'}
            </p>
            <DesignRow
              icon={icon('imgIconDisc1')}
              title="Mes collections"
              detail={`${data.collections.length} collections · privées`}
              to="/collections"
            />
            <h2>À la une</h2>
            <div className="design-grid">
              {data.collections.filter((c) => data.pins.includes(c.id)).map((c) => tile(c))}
            </div>
            {!data.pins.length && (
              <p className="design-hint">Épinglez vos collections préférées dans votre vitrine.</p>
            )}
            <details className="design-page-menu">
              <summary>Plus</summary>
              <div className="design-inline-links">
                <Link to="/vitrine">Personnaliser ma vitrine</Link>
                <Link to="/acces">Gérer les accès</Link>
                <Link to="/prets">Mes prêts</Link>
                <Link to="/compte">Mon compte</Link>
              </div>
            </details>
          </>
        )}
      </DesignPage>
    )

  if (view === 'edit')
    return (
      <DesignPage
        frame={frame}
        className="social-profile-edit"
        title="Modifier mon profil"
        subtitle="Photo, nom et présentation"
        footer={
          <button
            type="submit"
            form="profile-edit"
            className="btn btn-primary"
            disabled={busy || !data}
          >
            {busy ? 'Enregistrement…' : 'Enregistrer'}
          </button>
        }
      >
        {errors}
        {load}
        {data && (
          <DesignForm
            id="profile-edit"
            onSubmit={(e) => {
              e.preventDefault()
              void run(async () => {
                setData(
                  await social.updateWorkspace({ version: data.version, profile: currentProfile }),
                )
                navigate('/profil')
              })
            }}
          >
            <label className="social-avatar-picker">
              <span className="social-avatar">
                {currentProfile.photo ? (
                  <img src={currentProfile.photo} alt="Votre photo" />
                ) : (
                  icon('imgIconUser')
                )}
                <span className="social-avatar__camera">{icon('imgIconCamera')}</span>
              </span>
              <strong>Changer ma photo</strong>
              <input
                type="file"
                accept="image/*"
                className="sr-only"
                aria-label="Changer ma photo"
                onChange={(e) => {
                  const f = e.target.files?.[0]
                  if (f)
                    void resizeToBase64(f)
                      .then((photo) => setProfile({ ...currentProfile, photo }))
                      .catch((e) => setError(errorText(e)))
                }}
              />
            </label>
            <DesignField label="Nom affiché">
              <input
                required
                maxLength={100}
                value={currentProfile.displayName}
                onChange={(e) => setProfile({ ...currentProfile, displayName: e.target.value })}
              />
            </DesignField>
            <DesignField label="Nom d’utilisateur">
              <input value={`@${data.username ?? ''}`} readOnly />
            </DesignField>
            <p className="sr-only">Votre identifiant unique se gère dans Mon compte.</p>
            <DesignField label="Bio">
              <input
                maxLength={500}
                value={currentProfile.bio}
                onChange={(e) => setProfile({ ...currentProfile, bio: e.target.value })}
              />
            </DesignField>
            <DesignField label="Lien de profil">
              <input readOnly value="Profil privé · publication bientôt disponible" />
            </DesignField>
          </DesignForm>
        )}
      </DesignPage>
    )

  if (view === 'collections')
    return (
      <DesignPage
        frame={frame}
        className="social-collections"
        title="Mes collections"
        subtitle="Organiser vos objets et créations"
        footer={
          <Link className="btn btn-primary" to="/collections/nouvelle">
            Nouvelle collection
          </Link>
        }
      >
        {errors}
        {load}
        <div className="design-grid">{data?.collections.map((c) => tile(c))}</div>
        {data && !data.collections.length && (
          <p className="design-hint">Créez votre première collection pour regrouper vos objets.</p>
        )}
        <DesignRow
          icon={icon('imgIconLock')}
          title="Collections privées"
          detail="Seules les présentations publiées sont visibles"
        />
      </DesignPage>
    )

  if (view === 'create')
    return (
      <DesignPage
        frame={frame}
        className="social-collection-create"
        title="Créer une collection"
        subtitle="Regrouper sans déplacer les objets"
        footer={
          <button
            className="btn btn-primary"
            type="submit"
            form="collection-create"
            disabled={busy || !data}
          >
            {busy ? 'Création…' : 'Créer la collection'}
          </button>
        }
      >
        {errors}
        <DesignForm
          id="collection-create"
          onSubmit={(e) => {
            e.preventDefault()
            void run(async () => {
              setData(await social.createCollection(name, cover))
              navigate('/collections')
            })
          }}
        >
          <DesignField label="Nom de la collection">
            <input
              required
              maxLength={100}
              placeholder="Ex. Mes vinyles préférés"
              value={name}
              onChange={(e) => setName(e.target.value)}
            />
          </DesignField>
          <DesignField label="Photo de couverture">
            <span className="collection-cover">
              {cover ? <img src={cover} alt="Couverture choisie" /> : icon('imgIconCamera')}
              <strong>Ajouter une couverture</strong>
              <input
                className="sr-only"
                type="file"
                accept="image/*"
                onChange={(e) => {
                  const f = e.target.files?.[0]
                  if (f)
                    void resizeToBase64(f)
                      .then(setCover)
                      .catch((e) => setError(errorText(e)))
                }}
              />
            </span>
          </DesignField>
          <DesignRow
            icon={icon('imgIconLock')}
            title="Visibilité"
            detail="Moi uniquement · Par défaut"
          />
        </DesignForm>
      </DesignPage>
    )

  if (view === 'collection')
    return (
      <DesignPage
        frame={frame}
        title={collection?.name ?? 'Collection'}
        subtitle="Regrouper sans déplacer les objets"
        footer={
          <Link className="btn btn-secondary" to="/collections">
            Mes collections
          </Link>
        }
      >
        {errors}
        {load}
        {data && !collection && <p>Collection introuvable.</p>}
        {collection && (
          <>
            <DesignNotice>
              Cette collection est privée. Ajouter un article ne modifie ni son propriétaire ni son
              rangement.
            </DesignNotice>
            <h2>Articles de la collection</h2>
            {collection.itemIds.length === 0 && <p>Aucun article pour le moment.</p>}
            {items.map((item) => (
              <DesignRow
                key={item.id}
                title={item.name}
                detail={
                  collection.itemIds.includes(item.id)
                    ? 'Retirer de la collection'
                    : 'Ajouter à la collection'
                }
                icon={icon('imgIconDisc')}
                selected={collection.itemIds.includes(item.id)}
                disabled={busy}
                onClick={() =>
                  void run(async () =>
                    setData(
                      await social.collectionItem(
                        collection.id,
                        item.id,
                        collection.itemIds.includes(item.id),
                      ),
                    ),
                  )
                }
              />
            ))}
            <Link className="design-link" to="/dressing">
              Ouvrir mon inventaire
            </Link>
          </>
        )}
      </DesignPage>
    )

  return (
    <DesignPage
      frame={frame}
      className="social-showcase"
      title="Personnaliser ma vitrine"
      subtitle="Choisir ce qui apparaît sur le profil"
      footer={
        <button
          className="btn btn-primary"
          disabled={!data || busy}
          onClick={() =>
            void run(async () => {
              if (data) {
                setData(await social.updateWorkspace({ version: data.version, pins: currentPins }))
                setNotice('Vitrine enregistrée. Elle reste privée.')
                setPins(null)
              }
            })
          }
        >
          Enregistrer la vitrine
        </button>
      }
    >
      {errors}
      {load}
      <DesignRow
        icon={icon('imgIconEye')}
        title="Aperçu de ma vitrine"
        detail="Votre aperçu personnel · privé"
        to="/profil"
      />
      <h2>Éléments épinglés</h2>
      <div className="design-grid">
        {data?.collections
          .filter((c) => currentPins.includes(c.id))
          .sort((a, b) => currentPins.indexOf(a.id) - currentPins.indexOf(b.id))
          .map((c) => tile(c, () => setPins(currentPins.filter((id) => id !== c.id))))}
      </div>
      <DesignRow
        icon={icon('imgIconPin')}
        title="Ajouter une épingle"
        detail="Choisir un objet ou une collection"
        onClick={() => setChoosePin(!choosePin)}
      />
      {choosePin && (
        <div className="design-stack">
          {data?.collections.map((c) => (
            <DesignRow
              key={c.id}
              title={c.name}
              detail="Collection privée"
              selected={currentPins.includes(c.id)}
              onClick={() =>
                setPins(
                  currentPins.includes(c.id)
                    ? currentPins.filter((id) => id !== c.id)
                    : [...currentPins, c.id],
                )
              }
            />
          ))}
          {data?.collections.length === 0 && (
            <Link to="/collections/nouvelle">Créer une collection</Link>
          )}
        </div>
      )}
      <DesignRow
        icon={icon('imgIconEdit')}
        title="Réorganiser"
        detail="Inverser l’ordre des épingles"
        onClick={() => setPins([...currentPins].reverse())}
      />
    </DesignPage>
  )
}
