import FamilyVisibilityPanel from '../components/FamilyVisibilityPanel.tsx'
import { useAuth } from '../auth/useAuth.ts'
import { itemText } from '../lib/itemCatalog.ts'
import FigmaGlyph from '../components/FigmaGlyph.tsx'
import { errorText } from '../lib/errors.ts'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { deleteItem, getItem, updateItem } from '../api/items.ts'
import * as social from '../api/social.ts'
import type { ItemSummary } from '../types/item.ts'
import {
  DesignPage,
  DesignRow,
  DesignNotice,
  ErrorMessage,
  FrameIcon,
} from '../components/DesignPage.tsx'

export default function ItemDetailPage({
  view,
}: {
  view: 'detail' | 'actions' | 'visibility' | 'share' | 'archive'
}) {
  const { id } = useParams()
  const { activeProfile } = useAuth()
  const child = activeProfile?.role === 'child'
  const navigate = useNavigate()
  const [item, setItem] = useState<ItemSummary | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [verifiedOnly, setVerifiedOnly] = useState(false)
  const [approved, setApproved] = useState(false)
  const [shareUrl, setShareUrl] = useState('')
  const [confirm, setConfirm] = useState<'archive' | 'delete' | null>(null)
  const [collections, setCollections] = useState<social.Collection[] | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  useEffect(() => {
    let live = true
    getItem(Number(id))
      .then((d) => {
        if (live) {
          setItem(d.item)
        }
      })
      .catch((e) => {
        if (live) setError(errorText(e))
      })
    return () => {
      live = false
    }
  }, [id])
  const frame = {
    detail: '165:308',
    actions: '165:345',
    visibility: '165:177',
    share: '165:235',
    archive: '165:544',
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
  const base = `/articles/${id}`
  const feedback = (
    <>
      <ErrorMessage error={error} />
      {notice && <p role="status">{notice}</p>}
      {!item && <p role="status">{error ? 'Article indisponible.' : 'Chargement…'}</p>}
    </>
  )
  if (item?.canEdit === false && view !== 'detail')
    return (
      <DesignPage frame={frame} title={itemText('Objet partagé', 'Shared belonging')}>
        <DesignNotice>
          {itemText(
            'Cet objet est visible en consultation uniquement.',
            'You may view this belonging, but cannot edit it.',
          )}
        </DesignNotice>
        <Link to={base}>{itemText('Revenir à l’objet', 'Back to the item')}</Link>
      </DesignPage>
    )
  if (view === 'visibility' || (view === 'share' && (child || item?.ownerProfile.role === 'child')))
    return (
      <DesignPage
        frame={frame}
        title={itemText('Visibilité d’un article', 'Item visibility')}
        subtitle={itemText('Choisir précisément qui peut voir', 'Choose exactly who can view')}
      >
        {feedback}
        {item && <FamilyVisibilityPanel item={item} onSave={setItem} />}
        <Link className="design-link" to={base}>
          {itemText('Revenir à l’article', 'Back to the item')}
        </Link>
      </DesignPage>
    )
  if (view === 'detail')
    return (
      <DesignPage
        frame={frame}
        className="social-item"
        title="Fiche article"
        subtitle={`${item?.name ?? 'Article'} · ${item?.canEdit === false ? itemText('Consultation uniquement', 'View only') : itemText('Mon inventaire', 'My inventory')}`}
        footer={
          item?.canEdit === false ? (
            <Link className="btn btn-secondary" to="/dressing">
              {itemText('Revenir au dressing', 'Back to wardrobe')}
            </Link>
          ) : (
            <>
              <Link className="btn btn-primary" to={`${base}/visibilite`}>
                {itemText('Visibilité et partage', 'Visibility and sharing')}
              </Link>
              <Link className="btn btn-secondary" to={`${base}/actions`}>
                Autres actions
              </Link>
            </>
          )
        }
      >
        {feedback}
        {item && (
          <>
            <div className="social-item__photo">
              {item.photos[0] ? (
                <img src={item.photos[0].url} alt={item.name} />
              ) : (
                icon('imgIconShirt')
              )}
            </div>
            {item.author && (
              <p>
                <strong>{itemText('Auteur / autrice', 'Author')} : </strong>
                {item.author}
              </p>
            )}
            {item.artist && (
              <p>
                <strong>{itemText('Artiste / groupe', 'Artist / band')} : </strong>
                {item.artist}
              </p>
            )}
            {item.publisher && (
              <p>
                <strong>{itemText('Éditeur / label', 'Publisher / label')} : </strong>
                {item.publisher}
              </p>
            )}
            <DesignRow
              icon={icon('imgIconUser')}
              title="Propriétaire"
              detail={item.ownerProfile.name}
              to={item.canEdit === false ? undefined : '/profils'}
            />
            <DesignRow
              icon={icon('imgIconHouse')}
              title="Rangement"
              detail={
                [item.room?.name, ...(item.storageLocation?.path ?? [])]
                  .filter(Boolean)
                  .join(' · ') || 'Pas encore rangé'
              }
              to={item.canEdit === false ? undefined : `${base}/emplacement`}
            />
            <DesignRow
              icon={icon('imgIconLock')}
              title="Visibilité"
              detail={
                item.familyAudience === 'owner' || item.visibility === 'personal'
                  ? itemText('Moi uniquement', 'Only me')
                  : item.familyAudience === 'selected'
                    ? itemText('Personnes choisies', 'Selected people')
                    : item.familyAudience === 'shared'
                      ? itemText('Partagé avec moi', 'Shared with me')
                      : itemText('Mon foyer', 'My household')
              }
              to={item.canEdit === false ? undefined : `${base}/visibilite`}
            />
          </>
        )}
      </DesignPage>
    )

  if (view === 'actions')
    return (
      <DesignPage
        frame={frame}
        className="social-item-actions"
        title="Actions sur un article"
        subtitle="Modifier, archiver ou supprimer"
      >
        {feedback}
        {item && (
          <>
            <DesignRow
              icon={icon('imgIconEdit')}
              title="Modifier les détails"
              detail="Nom, photo, catégorie"
              to={`${base}/modifier`}
            />
            <DesignRow
              icon={icon('imgIconBox')}
              title="Déplacer"
              detail="Changer de rangement"
              to={`${base}/emplacement`}
            />
            {!child && (
              <DesignRow
                icon={icon('imgIconDisc')}
                title="Ajouter à une collection"
                detail="Sans supprimer l’article"
                disabled={busy}
                onClick={() =>
                  void run(async () => setCollections((await social.getWorkspace()).collections))
                }
              />
            )}
            {child && (
              <Link className="design-link" to="/enfant">
                {itemText('Gérer mes collections', 'Manage my collections')}
              </Link>
            )}
            {collections && (
              <div className="design-stack">
                {collections.map((c) => (
                  <DesignRow
                    key={c.id}
                    title={c.name}
                    detail={c.itemIds.includes(item.id) ? 'Déjà dans cette collection' : 'Ajouter'}
                    disabled={busy}
                    onClick={() =>
                      void run(async () => {
                        await social.collectionItem(c.id, item.id)
                        setNotice(`Article ajouté à ${c.name}.`)
                        setCollections(null)
                      })
                    }
                  />
                ))}
                {collections.length === 0 && (
                  <Link to="/collections/nouvelle">Créer une collection</Link>
                )}
              </div>
            )}
            {!child && (
              <DesignRow
                icon={icon('imgIconPin')}
                title="Épingler au profil"
                detail="Choisir une collection dans la vitrine"
                to="/vitrine"
              />
            )}
            <DesignRow
              icon={icon('imgIconArchive')}
              title={
                item.status === 'archive'
                  ? child
                    ? itemText('Demander à un parent de restaurer', 'Ask a parent to restore')
                    : 'Restaurer'
                  : 'Archiver'
              }
              disabled={child && item.status === 'archive'}
              detail="Récupérable plus tard"
              onClick={
                item.status === 'archive'
                  ? () =>
                      void run(async () => {
                        const result = await updateItem(item.id, { status: 'disponible' })
                        setItem(result.item)
                        setNotice('Article restauré.')
                      })
                  : () => navigate(`${base}/archiver`)
              }
            />
            {!child && (
              <DesignRow
                icon={icon('imgIconTrash')}
                title="Supprimer"
                detail="Confirmation requise"
                to={`${base}/archiver`}
              />
            )}
            <Link className="design-link" to={base}>
              Revenir à l’article
            </Link>
          </>
        )}
      </DesignPage>
    )

  if (view === 'share')
    return (
      <DesignPage
        frame={frame}
        className="social-share"
        title="Partager un article"
        subtitle="Partager sans révéler les détails privés"
        footer={
          <button
            className="btn btn-primary"
            disabled={!item || busy || !approved || !!shareUrl}
            onClick={() =>
              void run(async () => {
                if (!item) return
                const result = await social.createShare(item.id, verifiedOnly, approved)
                setShareUrl(`${window.location.origin}${result.path}`)
              })
            }
          >
            {busy ? 'Création…' : 'Partager'}
          </button>
        }
      >
        {feedback}
        <DesignRow
          icon={icon('imgIconShirt')}
          title={item?.name ?? 'Article'}
          detail="Photo et détails sélectionnés"
          to={base}
        />
        <DesignRow
          icon={icon('imgIconUsers')}
          title="Choisir mes amis"
          detail="Envoyer aux destinataires choisis"
          onClick={() =>
            setNotice(
              'L’envoi à des amis précis nécessite encore des autorisations par destinataire. Aucun accès n’a été accordé.',
            )
          }
        />
        <DesignRow
          icon={icon('imgIconLink')}
          title="Créer un lien"
          detail="Lien privé · Lecture seule · 7 jours"
          selected={verifiedOnly}
          onClick={() => setVerifiedOnly(!verifiedOnly)}
        />
        <label className="design-check">
          <input
            type="checkbox"
            checked={verifiedOnly}
            onChange={(e) => setVerifiedOnly(e.target.checked)}
          />
          Compte vérifié requis pour ouvrir le lien
        </label>
        <DesignNotice>
          <h2>Aperçu des informations visibles</h2>
          <ul>
            <li>
              <FigmaGlyph name="shareCheck" /> Photo, nom, type et couleur sélectionnés
            </li>
            <li>
              <FigmaGlyph name="shareCheck" /> Pas de prix d’achat
            </li>
            <li>
              <FigmaGlyph name="shareCheck" /> Pas de lieu exact ni adresse
            </li>
            <li>
              <FigmaGlyph name="shareCheck" /> Pas de notes personnelles
            </li>
          </ul>
          <p>
            {item?.name} {item?.type ? `· ${item.type}` : ''} {item?.color ? `· ${item.color}` : ''}
          </p>
        </DesignNotice>
        <label className="design-check">
          <input
            type="checkbox"
            checked={approved}
            onChange={(e) => setApproved(e.target.checked)}
          />
          J’approuve ces informations. Le lien peut être transféré et des copies conservées.
        </label>
        {shareUrl && (
          <DesignNotice>
            <label>
              Lien créé
              <input
                className="input"
                readOnly
                value={shareUrl}
                onFocus={(e) => e.target.select()}
              />
            </label>
            <button
              type="button"
              className="design-link"
              onClick={() =>
                void run(async () => {
                  await navigator.clipboard.writeText(shareUrl)
                  setNotice('Lien copié.')
                })
              }
            >
              Copier le lien
            </button>
            <Link to="/acces">Gérer et révoquer les accès</Link>
          </DesignNotice>
        )}
      </DesignPage>
    )

  return (
    <DesignPage
      frame={frame}
      className="social-archive"
      title="Archiver ou supprimer"
      subtitle="Choisir une action réversible ou définitive"
      footer={
        <Link className="btn btn-secondary" to={base}>
          Annuler
        </Link>
      }
    >
      {feedback}
      <DesignRow
        icon={icon('imgIconArchive')}
        title="Archiver"
        detail="Masquer sans supprimer"
        disabled={!item || busy}
        onClick={() => setConfirm('archive')}
      />
      {!child && (
        <DesignRow
          icon={icon('imgIconTrash')}
          title="Supprimer"
          detail="Confirmation requise · définitif"
          disabled={!item || busy}
          onClick={() => setConfirm('delete')}
        />
      )}
      <DesignNotice>
        <h2>{icon('imgIconShield')}Attention</h2>
        <p>
          Un prêt actif et les droits d’autres personnes doivent être traités avant la suppression.
        </p>
      </DesignNotice>
      {confirm && (
        <div
          role="alertdialog"
          aria-modal="true"
          aria-labelledby="item-confirm-title"
          className="design-confirm"
        >
          <h2 id="item-confirm-title">
            {confirm === 'archive'
              ? 'Archiver cet article ?'
              : 'Supprimer définitivement cet article ?'}
          </h2>
          <p>
            {confirm === 'archive'
              ? 'Vous pourrez le restaurer. Ses partages deviendront inaccessibles.'
              : 'Les photos et les liens seront supprimés. Cette action ne peut pas être annulée.'}
          </p>
          <button
            className="btn btn-primary"
            disabled={busy}
            onClick={() =>
              void run(async () => {
                if (!item) return
                if (confirm === 'delete') await deleteItem(item.id)
                else await updateItem(item.id, { status: 'archive' })
                navigate('/dressing')
              })
            }
          >
            Confirmer
          </button>
          <button className="btn btn-secondary" onClick={() => setConfirm(null)} disabled={busy}>
            Annuler
          </button>
        </div>
      )}
    </DesignPage>
  )
}

export function AccessPage() {
  const [now] = useState(() => Date.now())
  const [shares, setShares] = useState<social.Share[]>([])
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState<number | null>(null)
  const [loading, setLoading] = useState(true)
  useEffect(() => {
    let live = true
    social
      .listShares()
      .then((d) => {
        if (live) setShares(d.shares)
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
  return (
    <DesignPage
      frame="165:271"
      className="social-access"
      title="Gérer les accès"
      subtitle="Consulter et retirer les autorisations"
      footer={
        <Link className="btn btn-primary" to="/dressing">
          Créer un partage
        </Link>
      }
    >
      <ErrorMessage error={error} />
      {loading && <p role="status">Chargement…</p>}
      {!loading && !shares.length && <p>Aucun partage créé.</p>}
      {shares.map((s) => {
        const inactive = s.revoked || new Date(s.expiresAt).getTime() < now
        return (
          <DesignRow
            key={s.id}
            icon={<FrameIcon frame="165:271" name="imgIconLink" />}
            title={s.itemName}
            detail={
              inactive
                ? 'Révoqué ou expiré'
                : `Lien privé · ${s.verifiedOnly ? 'Compte vérifié' : 'Lecture seule'} · Révoquer`
            }
            disabled={inactive || busy !== null}
            onClick={() => {
              setBusy(s.id)
              setError(null)
              void social
                .revokeShare(s.id)
                .then(() =>
                  setShares((v) => v.map((x) => (x.id === s.id ? { ...x, revoked: true } : x))),
                )
                .catch((e) => setError(errorText(e)))
                .finally(() => setBusy(null))
            }}
          />
        )
      })}
      <DesignNotice>
        <h2>
          <FrameIcon frame="165:271" name="imgIconShield" />
          Révoquer un accès
        </h2>
        <p>Cela empêche les accès futurs, mais ne récupère pas les copies enregistrées.</p>
      </DesignNotice>
    </DesignPage>
  )
}
