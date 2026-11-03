import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { listPublications, revokePublication } from '../api/family.ts'
import type { Publication } from '../api/family.ts'
import ChildSnapshotPreview from '../components/ChildSnapshotPreview.tsx'
import { DesignPage, ErrorMessage } from '../components/DesignPage.tsx'
import { itemText as t } from '../lib/itemCatalog.ts'
import { errorText } from '../lib/errors.ts'

export default function FamilySharedPage() {
  const [rows, setRows] = useState<Publication[]>([])
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  useEffect(() => {
    let live = true
    listPublications()
      .then((data) => {
        if (live) setRows(data.publications)
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
      frame="165:8"
      title={t('Partages familiaux', 'Family shares')}
      subtitle={t('Uniquement les aperçus approuvés pour vous.', 'Only previews approved for you.')}
    >
      <ErrorMessage error={error} />
      {loading && <p role="status">{t('Chargement…', 'Loading…')}</p>}
      {!loading && rows.length === 0 && (
        <p>
          {t(
            'Aucun partage approuvé accessible pour le moment.',
            'No approved shares are available yet.',
          )}
        </p>
      )}
      {rows.map((row) => (
        <article className="family-section" key={row.id}>
          <h2>{row.name}</h2>
          {row.items.map((item, index) => (
            <ChildSnapshotPreview key={index} item={item} />
          ))}
          {row.canRevoke && (
            <button
              className="btn btn-secondary"
              disabled={busy}
              onClick={() => {
                setBusy(true)
                setError(null)
                void revokePublication(row.id)
                  .then(() => listPublications())
                  .then((data) => setRows(data.publications))
                  .catch((e) => setError(errorText(e)))
                  .finally(() => setBusy(false))
              }}
            >
              {t('Arrêter ce partage', 'Stop sharing')}
            </button>
          )}
        </article>
      ))}
      <Link className="design-link" to="/notifications">
        {t('Notifications', 'Notifications')}
      </Link>
    </DesignPage>
  )
}
