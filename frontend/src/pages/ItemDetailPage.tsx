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
              title={itemText('Propriétaire', 'Owner')}
              detail={item.ownerProfile.name}
              to={item.canEdit === false ? undefined : '/profils'}
            />
            <DesignRow
              icon={icon('imgIconHouse')}
              title={itemText('Rangement', 'Storage')}
              detail={
                [item.room?.name, ...(item.storageLocation?.path ?? [])]
                  .filter(Boolean)
                  .join(' · ') || itemText('Pas encore rangé', 'Not stored yet')
              }
              to={item.canEdit === false ? undefined : `${base}/emplacement`}
            />
            <DesignRow
              icon={icon('imgIconLock')}
              title={itemText('Visibilité', 'Visibility')}
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
        title={itemText('Actions sur un article', 'Item actions')}
        subtitle={itemText('Modifier, archiver ou supprimer', 'Edit, archive or delete')}
      >
        {feedback}
        {item && (
          <>
            <DesignRow
              icon={icon('imgIconEdit')}
              title={itemText('Modifier les détails', 'Edit details')}
              detail={itemText('Nom, photo, catégorie', 'Name, photo, category')}
              to={`${base}/modifier`}
            />
            <DesignRow
              icon={icon('imgIconBox')}
              title={itemText('Déplacer', 'Move')}
              detail={itemText('Changer de rangement', 'Change storage')}
              to={`${base}/emplacement`}
            />
            {!child && (
              <DesignRow
                icon={icon('imgIconDisc')}
                title={itemText('Ajouter à une collection', 'Add to a collection')}
                detail={itemText('Sans supprimer l’article', 'Without deleting the item')}
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
                    detail={c.itemIds.includes(item.id) ? itemText('Déjà dans cette collection', 'Already in this collection') : itemText('Ajouter', 'Add')}
                    disabled={busy}
                    onClick={() =>
                      void run(async () => {
                        await social.collectionItem(c.id, item.id)
                        setNotice(`${itemText('Article ajouté à', 'Item added to')} ${c.name}.`)
                        setCollections(null)
                      })
                    }
                  />
                ))}
                {collections.length === 0 && (
                  <Link to="/collections/nouvelle">{itemText('Créer une collection', 'Create a collection')}</Link>
                )}
              </div>
            )}
            {!child && (
              <DesignRow
                icon={icon('imgIconPin')}
                title={itemText('Épingler au profil', 'Pin to profile')}
                detail={itemText('Choisir une collection dans la vitrine', 'Choose a collection in the showcase')}
                to="/vitrine"
              />
            )}
            <DesignRow
              icon={icon('imgIconArchive')}
              title={
                item.status === 'archive'
                  ? child
                    ? itemText('Demander à un parent de restaurer', 'Ask a parent to restore')
                    : itemText('Restaurer', 'Restore')
                  : itemText('Archiver', 'Archive')
              }
              disabled={child && item.status === 'archive'}
              detail={itemText('Récupérable plus tard', 'Recoverable later')}
              onClick={
                item.status === 'archive'
                  ? () =>
                      void run(async () => {
                        const result = await updateItem(item.id, { status: 'disponible' })
                        setItem(result.item)
                        setNotice(itemText('Article restauré.', 'Item restored.'))
                      })
                  : () => navigate(`${base}/archiver`)
              }
            />
            {!child && (
              <DesignRow
                icon={icon('imgIconTrash')}
                title={itemText('Supprimer', 'Delete')}
                detail={itemText('Confirmation requise', 'Confirmation required')}
                to={`${base}/archiver`}
              />
            )}
            <Link className="design-link" to={base}>
              {itemText('Revenir à l’article', 'Back to the item')}
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
        title={itemText('Partager un article', 'Share an item')}
        subtitle={itemText('Partager sans révéler les détails privés', 'Share without revealing private details')}
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
            {busy ? itemText('Création…', 'Creating…') : itemText('Partager', 'Share')}
          </button>
        }
      >
        {feedback}
        <DesignRow
          icon={icon('imgIconShirt')}
          title={item?.name ?? itemText('Article', 'Item')}
          detail={itemText('Photo et détails sélectionnés', 'Selected photo and details')}
          to={base}
        />
        <DesignRow
          icon={icon('imgIconUsers')}
          title={itemText('Choisir mes amis', 'Choose my friends')}
          detail={itemText('Envoyer aux destinataires choisis', 'Send to selected recipients')}
          onClick={() =>
            setNotice(
              itemText('L’envoi à des amis précis nécessite encore des autorisations par destinataire. Aucun accès n’a été accordé.', 'Sending to specific friends still requires per-recipient permissions. No access was granted.'),
            )
          }
        />
        <DesignRow
          icon={icon('imgIconLink')}
          title={itemText('Créer un lien', 'Create a link')}
          detail={itemText('Lien privé · Lecture seule · 7 jours', 'Private link · Read only · 7 days')}
          selected={verifiedOnly}
          onClick={() => setVerifiedOnly(!verifiedOnly)}
        />
        <label className="design-check">
          <input
            type="checkbox"
            checked={verifiedOnly}
            onChange={(e) => setVerifiedOnly(e.target.checked)}
          />
          {itemText('Compte vérifié requis pour ouvrir le lien', 'Verified account required to open the link')}
        </label>
        <DesignNotice>
          <h2>{itemText('Aperçu des informations visibles', 'Preview of visible information')}</h2>
          <ul>
            <li>
              <FigmaGlyph name="shareCheck" /> {itemText('Photo, nom, type et couleur sélectionnés', 'Selected photo, name, type and colour')}
            </li>
            <li>
              <FigmaGlyph name="shareCheck" /> {itemText('Pas de prix d’achat', 'No purchase price')}
            </li>
            <li>
              <FigmaGlyph name="shareCheck" /> {itemText('Pas de lieu exact ni adresse', 'No exact location or address')}
            </li>
            <li>
              <FigmaGlyph name="shareCheck" /> {itemText('Pas de notes personnelles', 'No personal notes')}
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
          {itemText('J’approuve ces informations. Le lien peut être transféré et des copies conservées.', 'I approve this information. The link can be forwarded and copies kept.')}
        </label>
        {shareUrl && (
          <DesignNotice>
            <label>
              {itemText('Lien créé', 'Link created')}
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
                  setNotice(itemText('Lien copié.', 'Link copied.'))
                })
              }
            >
              {itemText('Copier le lien', 'Copy link')}
            </button>
            <Link to="/acces">{itemText('Gérer et révoquer les accès', 'Manage and revoke access')}</Link>
          </DesignNotice>
        )}
      </DesignPage>
    )

  return (
    <DesignPage
      frame={frame}
      className="social-archive"
      title={itemText('Archiver ou supprimer', 'Archive or delete')}
      subtitle={itemText('Choisir une action réversible ou définitive', 'Choose a reversible or permanent action')}
      footer={
        <Link className="btn btn-secondary" to={base}>
          {itemText('Annuler', 'Cancel')}
        </Link>
      }
    >
      {feedback}
      <DesignRow
        icon={icon('imgIconArchive')}
        title={itemText('Archiver', 'Archive')}
        detail={itemText('Masquer sans supprimer', 'Hide without deleting')}
        disabled={!item || busy}
        onClick={() => setConfirm('archive')}
      />
      {!child && (
        <DesignRow
          icon={icon('imgIconTrash')}
          title={itemText('Supprimer', 'Delete')}
          detail={itemText('Confirmation requise · définitif', 'Confirmation required · permanent')}
          disabled={!item || busy}
          onClick={() => setConfirm('delete')}
        />
      )}
      <DesignNotice>
        <h2>{icon('imgIconShield')}{itemText('Attention', 'Warning')}</h2>
        <p>
          {itemText('Un prêt actif et les droits d’autres personnes doivent être traités avant la suppression.', 'An active loan and other people’s rights must be handled before deletion.')}
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
              ? itemText('Archiver cet article ?', 'Archive this item?')
              : itemText('Supprimer définitivement cet article ?', 'Permanently delete this item?')}
          </h2>
          <p>
            {confirm === 'archive'
              ? itemText('Vous pourrez le restaurer. Ses partages deviendront inaccessibles.', 'You can restore it. Its shares will become inaccessible.')
              : itemText('Les photos et les liens seront supprimés. Cette action ne peut pas être annulée.', 'Photos and links will be deleted. This action cannot be undone.')}
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
            {itemText('Confirmer', 'Confirm')}
          </button>
          <button className="btn btn-secondary" onClick={() => setConfirm(null)} disabled={busy}>
            {itemText('Annuler', 'Cancel')}
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
      title={itemText('Gérer les accès', 'Manage access')}
      subtitle={itemText('Consulter et retirer les autorisations', 'View and revoke permissions')}
      footer={
        <Link className="btn btn-primary" to="/dressing">
          {itemText('Créer un partage', 'Create a share')}
        </Link>
      }
    >
      <ErrorMessage error={error} />
      {loading && <p role="status">{itemText('Chargement…', 'Loading…')}</p>}
      {!loading && !shares.length && <p>{itemText('Aucun partage créé.', 'No share created.')}</p>}
      {shares.map((s) => {
        const inactive = s.revoked || new Date(s.expiresAt).getTime() < now
        return (
          <DesignRow
            key={s.id}
            icon={<FrameIcon frame="165:271" name="imgIconLink" />}
            title={s.itemName}
            detail={
              inactive
                ? itemText('Révoqué ou expiré', 'Revoked or expired')
                : `${itemText('Lien privé', 'Private link')} · ${s.verifiedOnly ? itemText('Compte vérifié', 'Verified account') : itemText('Lecture seule', 'Read only')} · ${itemText('Révoquer', 'Revoke')}`
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
          {itemText('Révoquer un accès', 'Revoke access')}
        </h2>
        <p>{itemText('Cela empêche les accès futurs, mais ne récupère pas les copies enregistrées.', 'This prevents future access but does not recover saved copies.')}</p>
      </DesignNotice>
    </DesignPage>
  )
}
