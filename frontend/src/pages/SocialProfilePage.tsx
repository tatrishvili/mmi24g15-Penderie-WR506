import { Pin } from 'lucide-react'
import { audienceLabel, collectionItemDetail } from '../lib/collectionAudience.ts'
import { CollectionAudience, CollectionApproval } from '../components/CollectionAudience.tsx'
import { itemText as t } from '../lib/itemCatalog.ts'
import { errorText } from '../lib/errors.ts'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import * as social from '../api/social.ts'
import type {
  Workspace,
  Collection,
  CollectionAudience as Audience,
  CollectionPreview,
} from '../api/social.ts'
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
  const [audience, setAudience] = useState<Audience | null>(null)
  const [approved, setApproved] = useState(false)
  const [shareBio, setShareBio] = useState<boolean | null>(null)
  const [shareIdentity, setShareIdentity] = useState<boolean | null>(null)
  const [preview, setPreview] = useState<CollectionPreview | null>(null)
  const [profile, setProfile] = useState<Workspace['profile'] | null>(null)
  const [pins, setPins] = useState<string[] | null>(null)
  const [choosePin, setChoosePin] = useState(false)
  const [collectionFilter, setCollectionFilter] = useState<'all' | 'shared' | 'private'>('all')
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
  useEffect(() => {
    if (view !== 'collection' || !collectionId || !data) return
    let live = true
    void social
      .previewCollection(collectionId)
      .then((result) => {
        if (live) {
          setPreview(result)
          setApproved(false)
        }
      })
      .catch((e) => {
        if (live) setError(errorText(e))
      })
    return () => {
      live = false
    }
  }, [view, collectionId, data, setError])
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
  const chosenAudience = audience ?? collection?.visibility ?? 'personal'
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
      detail={`${c.itemIds.length} · ${audienceLabel(c.visibility)}`}
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
            <nav className="profile-connections" aria-label={t('Mes relations', 'My connections')}>
              <Link
                to="/amis?liste=amis"
                aria-label={`${data.connections?.friends ?? 0} ${t('Amis', 'Friends')}`}
              >
                <strong>{data.connections?.friends ?? 0}</strong>
                <span>{t('Amis', 'Friends')}</span>
              </Link>
              <Link
                to="/amis?liste=abonnes"
                aria-label={`${data.connections?.followers ?? 0} ${t('Abonnés', 'Followers')}`}
              >
                <strong>{data.connections?.followers ?? 0}</strong>
                <span>{t('Abonnés', 'Followers')}</span>
              </Link>
              <Link
                to="/amis?liste=abonnements"
                aria-label={`${data.connections?.following ?? 0} ${t('Abonnements', 'Following')}`}
              >
                <strong>{data.connections?.following ?? 0}</strong>
                <span>{t('Abonnements', 'Following')}</span>
              </Link>
            </nav>
            <p className="social-profile__bio">
              {data.profile.bio || 'Ajoutez quelques mots pour vous présenter.'}
            </p>
            <Link className="btn btn-secondary" to="/profil/modifier">
              {t('Modifier mon profil', 'Edit profile')}
            </Link>
            <DesignRow
              icon={icon('imgIconUser')}
              title={t('Famille et profils', 'Family and profiles')}
              detail={t('Voir les membres et ajouter un enfant', 'View members and add a child')}
              to="/foyer/membres"
            />
            <section
              className="profile-collections"
              aria-label={t('Mes collections', 'My collections')}
            >
              <div className="profile-collections__heading">
                <h2>
                  {t('Mes collections', 'My collections')}{' '}
                  <small>({data.collections.length})</small>
                </h2>
                <Link className="design-link" to="/collections/nouvelle">
                  {t('Créer', 'Create')}
                </Link>
              </div>
              <div
                className="feed-tabs"
                role="group"
                aria-label={t('Filtrer mes collections', 'Filter my collections')}
              >
                {(['all', 'shared', 'private'] as const).map((filter) => (
                  <button
                    key={filter}
                    aria-pressed={collectionFilter === filter}
                    onClick={() => setCollectionFilter(filter)}
                  >
                    {filter === 'all'
                      ? t('Toutes', 'All')
                      : filter === 'shared'
                        ? t('Partagées', 'Shared')
                        : t('Privées', 'Private')}
                  </button>
                ))}
              </div>
              <p className="design-hint">
                {t(
                  'Vos épingles apparaissent en premier. Les visiteurs voient uniquement les collections partagées avec eux.',
                  'Pins appear first. Visitors only see collections shared with them.',
                )}
              </p>
              <div className="profile-collection-grid">
                {data.collections
                  .filter(
                    (c) =>
                      collectionFilter === 'all' ||
                      (collectionFilter === 'private'
                        ? c.visibility === 'personal'
                        : c.visibility !== 'personal'),
                  )
                  .sort((a, b) => {
                    const rank = (id: string) =>
                      data.pins.includes(id) ? data.pins.indexOf(id) : data.pins.length
                    return rank(a.id) - rank(b.id)
                  })
                  .map((c) => (
                    <article key={c.id} className="profile-collection-card" aria-label={c.name}>
                      <Link
                        className="profile-collection-card__content"
                        to={`/collections/${c.id}`}
                      >
                        <span className="profile-collection-card__image">{collectionIcon(c)}</span>
                        <strong>{c.name}</strong>
                        <small>
                          {c.itemIds.length}{' '}
                          {c.itemIds.length === 1 ? t('article', 'item') : t('articles', 'items')}
                        </small>
                        <span className="profile-collection-card__audience">
                          {audienceLabel(c.visibility)}
                        </span>
                        {data.pins.includes(c.id) && (
                          <small className="profile-collection-card__pinned">
                            {t('Épinglée', 'Pinned')}
                          </small>
                        )}
                      </Link>
                      <button
                        type="button"
                        className="profile-collection-card__pin"
                        aria-label={`${data.pins.includes(c.id) ? t('Désépingler', 'Unpin') : t('Épingler', 'Pin')} ${c.name}`}
                        aria-pressed={data.pins.includes(c.id)}
                        disabled={busy || (!data.pins.includes(c.id) && data.pins.length >= 12)}
                        onClick={() =>
                          void run(async () => {
                            const removing = data.pins.includes(c.id)
                            const pins = removing
                              ? data.pins.filter((id) => id !== c.id)
                              : [c.id, ...data.pins]
                            setData(await social.updateWorkspace({ version: data.version, pins }))
                            setNotice(
                              removing
                                ? t('Collection désépinglée.', 'Collection unpinned.')
                                : t('Collection épinglée.', 'Collection pinned.'),
                            )
                          })
                        }
                      >
                        <Pin size={18} aria-hidden="true" />
                      </button>
                    </article>
                  ))}
              </div>
              {data.collections.length === 0 ? (
                <p className="design-hint">
                  {t(
                    'Créez votre première collection pour la retrouver ici.',
                    'Create your first collection to see it here.',
                  )}
                </p>
              ) : (
                !data.collections.some(
                  (c) =>
                    collectionFilter === 'all' ||
                    (collectionFilter === 'private'
                      ? c.visibility === 'personal'
                      : c.visibility !== 'personal'),
                ) && (
                  <p className="design-hint">
                    {t(
                      'Aucune collection dans cette sélection.',
                      'No collections match this filter.',
                    )}
                  </p>
                )
              )}
              {data.pins.length >= 12 && (
                <p className="design-hint">
                  {t(
                    'Vous avez épinglé 12 collections. Retirez une épingle pour en ajouter une autre.',
                    'You have pinned 12 collections. Remove a pin to add another.',
                  )}
                </p>
              )}
              <Link className="design-link" to="/collections">
                {t('Gérer mes collections', 'Manage my collections')}
              </Link>
            </section>
            <details className="design-page-menu">
              <summary>Plus</summary>
              <div className="design-inline-links">
                <Link to="/amis">{t('Amis et demandes', 'Friends and requests')}</Link>
                <Link to="/profils">{t('Changer de profil', 'Switch profile')}</Link>
                <Link to="/actualites">{t('Le fil Penderie', 'The Penderie feed')}</Link>
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
                  await social.updateWorkspace({
                    version: data.version,
                    profile: currentProfile,
                    shareIdentity: shareIdentity ?? data.identityPublished ?? false,
                    shareBio:
                      (shareIdentity ?? data.identityPublished ?? false) &&
                      (shareBio ?? data.bioPublished ?? false),
                  }),
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
            <label className="collection-approval">
              <input
                type="checkbox"
                checked={shareIdentity ?? data.identityPublished ?? false}
                onChange={(e) => setShareIdentity(e.target.checked)}
              />
              <span>
                {t(
                  'Afficher ce nom et cette photo aux membres Penderie',
                  'Show this name and photo to Penderie members',
                )}
              </span>
            </label>
            <label className="collection-approval">
              <input
                type="checkbox"
                checked={
                  (shareIdentity ?? data.identityPublished ?? false) &&
                  (shareBio ?? data.bioPublished ?? false)
                }
                disabled={!(shareIdentity ?? data.identityPublished ?? false)}
                onChange={(e) => setShareBio(e.target.checked)}
              />
              <span>
                {t(
                  'Afficher aussi ma bio sur mon profil Penderie',
                  'Also show my bio on my Penderie profile',
                )}
              </span>
            </label>
            <p className="design-hint">
              {t(
                'Votre nom, votre photo et votre bio sont partagés selon ces choix. Décochez une option puis enregistrez pour la retirer de votre profil Penderie.',
                'Your name, photo and bio are shared according to these choices. Uncheck an option and save to remove it from your Penderie profile.',
              )}
            </p>
            {data.username && (
              <Link
                className="design-link"
                to={`/communaute/profils/${encodeURIComponent(data.username)}`}
              >
                {t('Voir mon profil côté communauté', 'View my community profile')}
              </Link>
            )}
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
        <Link className="btn btn-secondary" to="/communaute/collections">
          {t('Explorer les collections', 'Explore collections')}
        </Link>
        <p className="design-hint">
          {t(
            'Chaque collection a sa propre audience : privée, amis, abonnés ou tout Penderie.',
            'Each collection has its own audience: private, friends, followers or everyone on Penderie.',
          )}
        </p>
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
            disabled={busy || !data || (chosenAudience !== 'personal' && !approved)}
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
              setData(await social.createCollection(name, cover, chosenAudience, approved))
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
          <CollectionAudience
            value={chosenAudience}
            onChange={(value) => {
              setAudience(value)
              setApproved(false)
            }}
            disabled={busy}
          />
          {chosenAudience !== 'personal' && (
            <>
              <p className="design-hint">
                {t(
                  'Le nom et la couverture seront visibles par cette audience. Les articles seront ajoutés à la publication après votre approbation depuis la collection.',
                  'This audience will see the name and cover. Items can be published after your approval from the collection.',
                )}
              </p>
              <CollectionApproval checked={approved} onChange={setApproved} disabled={busy} />
            </>
          )}
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
        {load}
        {data && !collection && <p>Collection introuvable.</p>}
        {collection && (
          <>
            <DesignNotice>
              {t(
                'Ajouter ou retirer un article remet la collection en privé. Vérifiez l’aperçu et publiez à nouveau. Le propriétaire et le rangement des articles restent inchangés.',
                'Adding or removing an item makes the collection private again. Review the preview and publish again. Item ownership and storage stay unchanged.',
              )}
            </DesignNotice>
            <CollectionAudience
              value={chosenAudience}
              onChange={(value) => {
                setAudience(value)
                setApproved(false)
              }}
              disabled={busy}
            />
            <p className="design-hint">
              {t(
                'Amis : relations acceptées. Abonnés : personnes qui vous suivent et amis. Tout Penderie : comptes adultes vérifiés connectés.',
                'Friends: accepted friendships. Followers: people following you and friends. Everyone on Penderie: signed-in verified adult accounts.',
              )}
            </p>
            {chosenAudience !== 'personal' && (
              <section
                className="collection-publication-preview"
                aria-label={t('Aperçu du partage', 'Sharing preview')}
              >
                <h2>{t('Aperçu du partage', 'Sharing preview')}</h2>
                {preview ? (
                  <>
                    <h3>{preview.name}</h3>
                    {preview.cover && (
                      <img className="collection-public-cover" src={preview.cover} alt="" />
                    )}
                    <div className="design-grid">
                      {preview.items.map((item, index) => (
                        <DesignTile
                          key={index}
                          title={item.name}
                          detail={collectionItemDetail(item)}
                          icon={item.photoUrl ? <img src={item.photoUrl} alt="" /> : null}
                        />
                      ))}
                    </div>
                    {preview.excludedCount > 0 && (
                      <p>
                        {t('Articles exclus du partage', 'Items excluded from sharing')} :{' '}
                        {preview.excludedCount}
                      </p>
                    )}
                    <p className="design-hint">
                      {t(
                        'Votre nom de collection, sa couverture et les articles de cet aperçu seront visibles par l’audience choisie. Vos notes et emplacements restent privés.',
                        'Your collection name, cover and the items in this preview will be visible to the selected audience. Your notes and storage locations stay private.',
                      )}
                    </p>
                    <p className="design-hint">
                      {t(
                        'Cliquez sur Partager pour confirmer cet aperçu.',
                        'Click Share to approve this preview.',
                      )}
                    </p>
                  </>
                ) : (
                  <p role="status">{t('Chargement de l’aperçu…', 'Loading preview…')}</p>
                )}
              </section>
            )}
            <button
              className="btn btn-primary"
              disabled={busy || (chosenAudience !== 'personal' && !preview)}
              onClick={() =>
                void run(async () => {
                  if (!data) return
                  await social.setCollectionAudience(
                    collection.id,
                    data.version,
                    chosenAudience,
                    chosenAudience !== 'personal',
                    preview?.digest,
                  )
                  setData(await social.getWorkspace())
                  setAudience(null)
                  setNotice(
                    chosenAudience === 'personal'
                      ? t('La collection est privée.', 'The collection is private.')
                      : t('Collection partagée avec succès.', 'Collection shared successfully.'),
                  )
                })
              }
            >
              {busy
                ? t('Enregistrement…', 'Saving…')
                : chosenAudience === 'personal'
                  ? t('Garder la collection privée', 'Keep collection private')
                  : chosenAudience === 'friends'
                    ? t('Partager avec mes amis', 'Share with my friends')
                    : chosenAudience === 'followers'
                      ? t('Partager avec mes abonnés', 'Share with my followers')
                      : t('Partager sur Penderie', 'Share on Penderie')}
            </button>
            <ErrorMessage error={error} />
            {notice && (
              <p className="collection-sharing-status" role="status">
                {notice}
              </p>
            )}
            {collection.visibility !== 'personal' && (
              <Link className="design-link" to={`/communaute/collections/${collection.id}`}>
                {t('Voir la collection partagée', 'View shared collection')}
              </Link>
            )}
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
      subtitle={t('Organiser mes collections épinglées', 'Organize my pinned collections')}
      footer={
        <button
          className="btn btn-primary"
          disabled={!data || busy}
          onClick={() =>
            void run(async () => {
              if (data) {
                setData(await social.updateWorkspace({ version: data.version, pins: currentPins }))
                setNotice(t('Épingles enregistrées.', 'Pins saved.'))
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
        detail={t(
          'Les visiteurs voient seulement les collections partagées avec eux',
          'Visitors only see collections shared with them',
        )}
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
        detail={t('Choisir une collection', 'Choose a collection')}
        onClick={() => setChoosePin(!choosePin)}
      />
      {choosePin && (
        <div className="design-stack">
          {data?.collections.map((c) => (
            <DesignRow
              key={c.id}
              title={c.name}
              detail={audienceLabel(c.visibility)}
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
