import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import * as family from '../api/family.ts'
import { listAllItems } from '../api/items.ts'
import { useAuth } from '../auth/useAuth.ts'
import ChildSnapshotPreview from '../components/ChildSnapshotPreview.tsx'
import { DesignPage, DesignForm, DesignField, ErrorMessage } from '../components/DesignPage.tsx'
import { errorText } from '../lib/errors.ts'
import { itemText as t } from '../lib/itemCatalog.ts'
import type { ItemSummary } from '../types/item.ts'

export default function ChildHubPage() {
  const auth = useAuth()
  const [query] = useSearchParams()
  const parent = auth.activeProfile?.role !== 'child'
  const childId =
    parent && query.get('enfant') ? Number(query.get('enfant')) : (auth.activeProfileId ?? 0)
  const [items, setItems] = useState<ItemSummary[]>([])
  const [collections, setCollections] = useState<family.ChildCollection[]>([])
  const [connections, setConnections] = useState<family.Connection[]>([])
  const [publications, setPublications] = useState<family.Publication[]>([])
  const [name, setName] = useState('')
  const [editing, setEditing] = useState<family.ChildCollection | null>(null)
  const [selectedItems, setSelectedItems] = useState<number[]>([])
  const [username, setUsername] = useState('')
  const [relative, setRelative] = useState(false)
  const [sourceKey, setSourceKey] = useState('')
  const [recipients, setRecipients] = useState<string[]>([])
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)
  const [loading, setLoading] = useState(true)
  const load = async () => {
    const [owned, saved, contacts, shared] = await Promise.all([
      listAllItems(),
      family.listCollections(parent ? childId : undefined),
      family.listConnections(),
      family.listPublications(),
    ])
    setItems(owned.filter((item) => item.ownerProfile.id === childId && item.status !== 'archive'))
    setCollections(saved.collections)
    setConnections(
      contacts.connections.filter(
        (contact) => contact.requesterId === childId || contact.recipientId === childId,
      ),
    )
    setPublications(shared.publications)
  }
  useEffect(() => {
    let live = true
    Promise.all([
      listAllItems(),
      family.listCollections(parent ? childId : undefined),
      family.listConnections(),
      family.listPublications(),
    ])
      .then(([owned, saved, contacts, shared]) => {
        if (live) {
          setItems(
            owned.filter((item) => item.ownerProfile.id === childId && item.status !== 'archive'),
          )
          setCollections(saved.collections)
          setConnections(
            contacts.connections.filter(
              (contact) => contact.requesterId === childId || contact.recipientId === childId,
            ),
          )
          setPublications(shared.publications)
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
  }, [childId, parent])
  async function run(action: () => Promise<unknown>, message: string) {
    if (busy) return
    setBusy(true)
    setError(null)
    setNotice('')
    try {
      await action()
      await load()
      setNotice(message)
    } catch (e) {
      setError(errorText(e))
    } finally {
      setBusy(false)
    }
  }
  const activeContacts = connections
    .filter((contact) => contact.state === 'active')
    .map((contact) => ({
      key: contact.requesterId === childId ? contact.recipientKey : contact.requesterKey,
      name: contact.requesterId === childId ? contact.recipientName : contact.requesterName,
    }))
  const sources = [
    ...items.map((item) => ({
      key: `item:${item.id}`,
      kind: 'item',
      id: item.id,
      version: item.sharingVersion ?? 1,
      name: item.name,
      items: [item],
    })),
    ...collections.map((collection) => ({
      key: `collection:${collection.id}`,
      kind: 'collection',
      id: collection.id,
      version: collection.version,
      name: collection.name,
      items: items.filter((item) => collection.itemIds.includes(item.id)),
    })),
  ]
  const source = sources.find((value) => value.key === sourceKey)
  return (
    <DesignPage
      frame="165:8"
      title={
        parent
          ? t('Gérer le dressing de mon enfant', 'Manage my child’s wardrobe')
          : t('Mon espace', 'My space')
      }
      subtitle={t(
        'Tes objets et collections sont privés. Tes parents responsables peuvent les gérer.',
        'Your belongings and collections are private. Your responsible parents can manage them.',
      )}
    >
      <ErrorMessage error={error} />
      {notice && <p role="status">{notice}</p>}
      {loading && <p role="status">{t('Chargement…', 'Loading…')}</p>}
      <div className="family-actions">
        <Link className="btn btn-primary" to="/dressing">
          {t('Dressing', 'Wardrobe')}
        </Link>
        <Link className="btn btn-secondary" to="/scanner">
          {t('Scanner', 'Scan')}
        </Link>
        <Link className="btn btn-secondary" to="/notifications">
          {t('Notifications', 'Notifications')}
        </Link>
      </div>
      <h2>{t('Mes objets', 'My belongings')}</h2>
      <div className="family-object-grid">
        {items.map((item) => (
          <Link className="family-item-link" key={item.id} to={`/articles/${item.id}`}>
            <strong>{item.name}</strong>
            {item.photos[0] && <img src={item.photos[0].url} alt={item.name} />}
          </Link>
        ))}
      </div>
      {!loading && items.length === 0 && (
        <p>
          {t(
            'Ajoute ton premier objet dans le dressing ou avec le scanner.',
            'Add your first belonging in the wardrobe or with the scanner.',
          )}
        </p>
      )}
      <h2>{t('Collections privées', 'Private collections')}</h2>
      {collections.map((collection) => (
        <div className="family-room-row" key={collection.id}>
          <span>
            {collection.name} · {collection.itemIds.length} {t('objet(s)', 'item(s)')}
          </span>
          <button
            className="design-link"
            type="button"
            onClick={() => {
              setEditing(collection)
              setName(collection.name)
              setSelectedItems(collection.itemIds)
            }}
          >
            {t('Modifier', 'Edit')}
          </button>
        </div>
      ))}
      <DesignForm
        id="child-collection"
        onSubmit={(e) => {
          e.preventDefault()
          void run(
            async () => {
              await family.saveCollection(
                {
                  name,
                  itemIds: selectedItems,
                  childId: parent ? childId : undefined,
                  version: editing?.version,
                },
                editing?.id,
              )
              setName('')
              setSelectedItems([])
              setEditing(null)
            },
            t('Collection privée enregistrée.', 'Private collection saved.'),
          )
        }}
      >
        <DesignField label={t('Nom de la collection', 'Collection name')}>
          <input required maxLength={100} value={name} onChange={(e) => setName(e.target.value)} />
        </DesignField>
        <fieldset>
          <legend>{t('Objets de cette collection', 'Belongings in this collection')}</legend>
          {items.map((item) => (
            <label className="family-choice" key={item.id}>
              <input
                type="checkbox"
                checked={selectedItems.includes(item.id)}
                onChange={(e) =>
                  setSelectedItems((ids) =>
                    e.target.checked ? [...ids, item.id] : ids.filter((id) => id !== item.id),
                  )
                }
              />
              {item.name}
            </label>
          ))}
        </fieldset>
        <button className="btn btn-primary" disabled={busy || loading}>
          {editing
            ? t('Enregistrer la collection', 'Save collection')
            : t('Créer une collection privée', 'Create a private collection')}
        </button>
        {editing && (
          <button
            type="button"
            className="design-link"
            onClick={() => {
              setEditing(null)
              setName('')
              setSelectedItems([])
            }}
          >
            {t('Annuler', 'Cancel')}
          </button>
        )}
      </DesignForm>
      <h2>{t('Contacts approuvés', 'Approved contacts')}</h2>
      {connections.map((connection) => (
        <div className="family-room-row" key={connection.id}>
          <div>
            <strong>
              {connection.requesterId === childId
                ? connection.recipientName
                : connection.requesterName}
            </strong>
            <p>
              {connection.state === 'active'
                ? t('Contact approuvé', 'Approved contact')
                : connection.state === 'pending'
                  ? t(
                      'Acceptation et accord parental en attente',
                      'Awaiting acceptance and parent approval',
                    )
                  : t('Contact retiré', 'Contact removed')}
            </p>
          </div>
          <div className="family-actions">
            {!parent &&
              connection.recipientId === childId &&
              connection.state === 'pending' &&
              !connection.recipientAccepted && (
                <button
                  className="btn btn-secondary btn-inline"
                  disabled={busy}
                  onClick={() =>
                    void run(
                      () => family.connectionAction(connection.id, 'accept'),
                      t(
                        'Contact accepté. L’accord parental reste nécessaire.',
                        'Contact accepted. Parent approval is still required.',
                      ),
                    )
                  }
                >
                  {t('Accepter', 'Accept')}
                </button>
              )}
            {connection.state !== 'revoked' && (
              <button
                className="design-link"
                disabled={busy}
                onClick={() =>
                  void run(
                    () => family.connectionAction(connection.id, 'revoke'),
                    t(
                      'Contact retiré. Les partages ne sont plus accessibles.',
                      'Contact removed. Shares are no longer accessible.',
                    ),
                  )
                }
              >
                {t('Retirer', 'Remove')}
              </button>
            )}
          </div>
        </div>
      ))}
      <DesignForm
        id="child-contact"
        onSubmit={(e) => {
          e.preventDefault()
          void run(
            async () => {
              await family.requestConnection(username, relative, parent ? childId : undefined)
              setUsername('')
            },
            t(
              'Demande envoyée. L’acceptation du contact et l’accord des parents sont nécessaires.',
              'Request sent. Contact acceptance and parent approval are required.',
            ),
          )
        }}
      >
        <DesignField label={t('Nom d’utilisateur du contact', 'Contact username')}>
          <input
            required
            maxLength={30}
            value={username}
            onChange={(e) => setUsername(e.target.value)}
          />
        </DesignField>
        <label className="family-choice">
          <input
            type="checkbox"
            checked={relative}
            onChange={(e) => setRelative(e.target.checked)}
          />
          {t('Ce contact est un adulte de ma famille', 'This contact is an adult relative')}
        </label>
        <button className="btn btn-secondary" disabled={busy}>
          {t('Demander ce contact', 'Request this contact')}
        </button>
      </DesignForm>
      <h2>{t('Partager avec mes contacts', 'Share with my contacts')}</h2>
      <DesignForm
        id="child-publication"
        onSubmit={(e) => {
          e.preventDefault()
          if (!source) return
          void run(
            () =>
              family.requestPublication({
                kind: source.kind,
                sourceId: source.id,
                version: source.version,
                recipients,
                childId: parent ? childId : undefined,
              }),
            t(
              'Aperçu envoyé aux parents. Rien n’est publié avant leur accord.',
              'Preview sent to parents. Nothing is published before approval.',
            ),
          )
        }}
      >
        <DesignField label={t('Objet ou collection', 'Item or collection')}>
          <select required value={sourceKey} onChange={(e) => setSourceKey(e.target.value)}>
            <option value="">{t('Choisir', 'Choose')}</option>
            {sources.map((value) => (
              <option key={value.key} value={value.key}>
                {value.name}
              </option>
            ))}
          </select>
        </DesignField>
        {source && (
          <div>
            <h3>{source.name}</h3>
            {source.items.map((item) => (
              <ChildSnapshotPreview key={item.id} item={item} />
            ))}
          </div>
        )}
        <fieldset>
          <legend>
            {t('Contacts qui pourront voir cet aperçu', 'Contacts who may see this preview')}
          </legend>
          {activeContacts.map((contact) => (
            <label className="family-choice" key={contact.key}>
              <input
                type="checkbox"
                checked={recipients.includes(contact.key)}
                onChange={(e) =>
                  setRecipients((values) =>
                    e.target.checked
                      ? [...values, contact.key]
                      : values.filter((value) => value !== contact.key),
                  )
                }
              />
              {contact.name}
            </label>
          ))}
        </fieldset>
        {activeContacts.length === 0 && (
          <p>
            {t(
              'Un contact doit d’abord être accepté et approuvé.',
              'A contact must first be accepted and approved.',
            )}
          </p>
        )}
        <button className="btn btn-primary" disabled={busy || !source || recipients.length === 0}>
          {t('Demander un accord pour cet aperçu', 'Request approval for this preview')}
        </button>
      </DesignForm>
      <h2>{t('Partages accessibles', 'Available shares')}</h2>
      {publications.map((publication) => (
        <article className="family-section" key={publication.id}>
          <h3>{publication.name}</h3>
          {publication.items.map((item, index) => (
            <ChildSnapshotPreview key={index} item={item} />
          ))}
          {publication.canRevoke && (
            <button
              className="btn btn-secondary"
              disabled={busy}
              onClick={() =>
                void run(
                  () => family.revokePublication(publication.id),
                  t('Partage retiré.', 'Share withdrawn.'),
                )
              }
            >
              {t('Arrêter ce partage', 'Stop sharing')}
            </button>
          )}
        </article>
      ))}
      <button className="design-link" onClick={() => void auth.logout()}>
        {t('Se déconnecter', 'Sign out')}
      </button>
    </DesignPage>
  )
}
