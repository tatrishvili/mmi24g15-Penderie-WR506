import { useAuth } from '../auth/useAuth.ts'
import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { ScanLine, Search } from 'lucide-react'
import { listItems } from '../api/items.ts'
import { ApiError } from '../api/client.ts'
import type { ItemSummary } from '../types/item.ts'
import { conditionLabel, statusLabel } from '../types/item.ts'
import { itemText as tr } from '../lib/itemCatalog.ts'

/**
 * "Mon dressing" — searchable list of the active foyer's items,
 * with a shortcut to add one through the scanner.
 */
export default function ItemsPage() {
  const [searchParams] = useSearchParams()
  const { activeProfile } = useAuth()
  const child = activeProfile?.role === 'child'
  const [items, setItems] = useState<ItemSummary[]>([])
  const [total, setTotal] = useState(0)
  const [query, setQuery] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(
    async (q: string, signal?: AbortSignal) => {
      setLoading(true)
      setError(null)

      try {
        const result = await listItems({
          q,
          roomId: searchParams.get('room') ? Number(searchParams.get('room')) : undefined,
          status: searchParams.get('status') === 'archive' ? 'archive' : 'active',
        })
        if (signal?.aborted === true) return
        setItems(result.items)
        setTotal(result.total)
      } catch (err) {
        if (signal?.aborted === true) return
        setError(
          err instanceof ApiError
            ? err.message
            : tr('Une erreur inattendue est survenue.', 'Something unexpected happened.'),
        )
      } finally {
        if (signal?.aborted !== true) setLoading(false)
      }
    },
    [searchParams],
  )

  useEffect(() => {
    const controller = new AbortController()
    // Initial data fetch: loading flag reset is intentional here.
    // eslint-disable-next-line react/set-state-in-effect
    void load('', controller.signal)

    return () => controller.abort()
  }, [load])

  function onSearch(event: FormEvent) {
    event.preventDefault()
    void load(query.trim())
  }

  const thumb = (item: ItemSummary): string | null => {
    const primary = item.photos.find((photo) => photo.isPrimary) ?? item.photos[0]
    return primary?.url ?? null
  }

  return (
    <div className="auth-page">
      <p className="auth-tagline">
        {tr('Tout ce que vous possédez, au même endroit.', 'Everything you own, in one place.')}
      </p>

      <div className="auth-card">
        <h1>
          {searchParams.get('status') === 'archive'
            ? tr('Articles archivés', 'Archived items')
            : tr('Mon dressing', 'My wardrobe')}
        </h1>
        <div className="design-inline-links">
          <Link
            to={searchParams.get('status') === 'archive' ? '/dressing' : '/dressing?status=archive'}
          >
            {searchParams.get('status') === 'archive'
              ? tr('Mon dressing actif', 'Active wardrobe')
              : tr('Voir les archives', 'View archive')}
          </Link>
          <Link to="/articles/nouveau">{tr('Ajouter manuellement', 'Add manually')}</Link>
          {!child && <Link to="/prets">{tr('Mes prêts', 'My loans')}</Link>}
        </div>

        <form className="auth-form" onSubmit={onSearch} noValidate>
          <div className="field">
            <label htmlFor="items-search">{tr('Rechercher', 'Search')}</label>
            <input
              id="items-search"
              className="input"
              type="search"
              placeholder={tr('Pull noir, Zara…', 'Black sweater, Zara…')}
              autoComplete="off"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
            />
          </div>
          <button type="submit" className="btn btn-secondary" disabled={loading}>
            <Search size={18} aria-hidden="true" /> {tr('Rechercher', 'Search')}
          </button>
        </form>

        {error !== null && (
          <p role="alert" className="form-error" style={{ marginTop: 12 }}>
            {error}
          </p>
        )}

        {loading ? (
          <p className="auth-sub">{tr('Chargement…', 'Loading…')}</p>
        ) : items.length === 0 ? (
          <>
            <p className="auth-sub">
              {total === 0
                ? tr(
                    'Votre dressing est encore vide. Scannez votre premier article !',
                    'Your wardrobe is still empty. Scan your first item!',
                  )
                : tr(
                    'Aucun article ne correspond à cette recherche.',
                    'No item matches this search.',
                  )}
            </p>
            <Link className="btn btn-primary" to="/scanner">
              <ScanLine size={18} aria-hidden="true" /> {tr('Scanner un article', 'Scan an item')}
            </Link>
          </>
        ) : (
          <>
            <p className="auth-sub">
              {total} article{total > 1 ? 's' : ''}
            </p>
            <ul className="profile-list">
              {items.map((item) => (
                <li key={item.id} className="profile-row">
                  {thumb(item) !== null ? (
                    <img
                      src={thumb(item) ?? ''}
                      alt=""
                      className="avatar"
                      style={{ objectFit: 'cover' }}
                    />
                  ) : (
                    <span className="avatar" aria-hidden="true">
                      {item.name.charAt(0).toUpperCase()}
                    </span>
                  )}
                  <span className="profile-meta">
                    <Link className="profile-name" to={`/articles/${item.id}`}>
                      {item.name}
                    </Link>
                    <br />
                    <span className="profile-role">
                      {item.room === null
                        ? tr('Emplacement inconnu', 'Unknown location')
                        : [
                            item.room.place.name,
                            item.room.name,
                            ...(item.storageLocation?.path ?? []),
                          ].join(' › ')}
                    </span>
                    <br />
                    <span className="profile-role">
                      {[item.category, item.color, item.size].filter(Boolean).join(' · ') || '—'}
                      {' · '}
                      {conditionLabel(item.condition)}
                    </span>
                  </span>
                  <span className="item-actions">
                    <span className="item-status" data-status={item.status}>
                      {statusLabel(item.status)}
                    </span>
                    {!child &&
                      item.ownerProfile.role !== 'child' &&
                      item.canEdit !== false &&
                      item.status === 'disponible' && (
                        <Link
                          className="btn btn-secondary btn-inline"
                          to={`/articles/${item.id}/partager?purpose=loan`}
                        >
                          {tr('Proposer un prêt', 'Offer a loan')}
                        </Link>
                      )}
                    {!child &&
                      item.ownerProfile.role !== 'child' &&
                      item.canEdit !== false &&
                      item.status === 'loue' && (
                        <Link className="btn btn-primary btn-inline" to="/prets">
                          {tr('Voir le prêt', 'View loan')}
                        </Link>
                      )}
                  </span>
                </li>
              ))}
            </ul>
            <Link className="btn btn-primary" to="/scanner">
              <ScanLine size={18} aria-hidden="true" /> {tr('Scanner un article', 'Scan an item')}
            </Link>
          </>
        )}
      </div>
    </div>
  )
}
