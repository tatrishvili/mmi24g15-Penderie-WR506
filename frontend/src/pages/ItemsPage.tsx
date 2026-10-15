import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { ScanLine, Search } from 'lucide-react'
import { listItems, updateItem } from '../api/items.ts'
import { ApiError } from '../api/client.ts'
import type { ItemStatus, ItemSummary } from '../types/item.ts'
import { conditionLabel, statusLabel } from '../types/item.ts'

/**
 * "Mon dressing" — searchable list of the active foyer's items,
 * with a shortcut to add one through the scanner.
 */
export default function ItemsPage() {
  const [items, setItems] = useState<ItemSummary[]>([])
  const [total, setTotal] = useState(0)
  const [query, setQuery] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  const load = useCallback(async (q: string, signal?: AbortSignal) => {
    setLoading(true)
    setError(null)

    try {
      const result = await listItems({ q })
      if (signal?.aborted === true) return
      setItems(result.items)
      setTotal(result.total)
    } catch (err) {
      if (signal?.aborted === true) return
      setError(err instanceof ApiError ? err.message : 'Une erreur inattendue est survenue.')
    } finally {
      if (signal?.aborted !== true) setLoading(false)
    }
  }, [])

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

  async function changeStatus(item: ItemSummary, status: ItemStatus): Promise<void> {
    setBusyId(item.id)
    setActionError(null)

    try {
      await updateItem(item.id, { status })
      await load(query.trim())
    } catch (err) {
      setActionError(err instanceof ApiError ? err.message : 'Une erreur inattendue est survenue.')
    } finally {
      setBusyId(null)
    }
  }

  const thumb = (item: ItemSummary): string | null => {
    const primary = item.photos.find((photo) => photo.isPrimary) ?? item.photos[0]
    return primary?.url ?? null
  }

  return (
    <div className="auth-page">
      <p className="auth-tagline">Tout ce que vous possédez, au même endroit.</p>

      <div className="auth-card">
        <h1>Mon dressing</h1>

        <form className="auth-form" onSubmit={onSearch} noValidate>
          <div className="field">
            <label htmlFor="items-search">Rechercher</label>
            <input
              id="items-search"
              className="input"
              type="search"
              placeholder="Pull noir, Zara…"
              autoComplete="off"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
            />
          </div>
          <button type="submit" className="btn btn-secondary" disabled={loading}>
            <Search size={18} aria-hidden="true" /> Rechercher
          </button>
        </form>

        {error !== null && (
          <p role="alert" className="form-error" style={{ marginTop: 12 }}>
            {error}
          </p>
        )}

        {actionError !== null && (
          <p role="alert" className="form-error" style={{ marginTop: 12 }}>
            {actionError}
          </p>
        )}

        {loading ? (
          <p className="auth-sub">Chargement…</p>
        ) : items.length === 0 ? (
          <>
            <p className="auth-sub">
              {total === 0
                ? 'Votre dressing est encore vide. Scannez votre premier article !'
                : 'Aucun article ne correspond à cette recherche.'}
            </p>
            <Link className="btn btn-primary" to="/scanner">
              <ScanLine size={18} aria-hidden="true" /> Scanner un article
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
                    <span className="profile-name">{item.name}</span>
                    <br />
                    <span className="profile-role">
                      {item.room === null
                        ? 'Emplacement inconnu'
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
                    {item.status === 'disponible' && (
                      <button
                        type="button"
                        className="btn btn-secondary btn-inline"
                        disabled={busyId === item.id}
                        onClick={() => void changeStatus(item, 'loue')}
                      >
                        Prêter
                      </button>
                    )}
                    {item.status === 'loue' && (
                      <button
                        type="button"
                        className="btn btn-primary btn-inline"
                        disabled={busyId === item.id}
                        onClick={() => void changeStatus(item, 'disponible')}
                      >
                        Récupérer
                      </button>
                    )}
                  </span>
                </li>
              ))}
            </ul>
            <Link className="btn btn-primary" to="/scanner">
              <ScanLine size={18} aria-hidden="true" /> Scanner un article
            </Link>
          </>
        )}
      </div>
    </div>
  )
}
