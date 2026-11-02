import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { discoverCollections, getPublishedCollection } from '../api/social.ts'
import type { PublishedCollection } from '../api/social.ts'
import { DesignPage, DesignTile, ErrorMessage } from '../components/DesignPage.tsx'
import { audienceLabel, collectionItemDetail } from '../lib/collectionAudience.ts'
import { itemText as t } from '../lib/itemCatalog.ts'
import { errorText } from '../lib/errors.ts'

export default function CommunityCollectionsPage() {
  const { collectionId } = useParams()
  const [collections, setCollections] = useState<PublishedCollection[]>([])
  const [collection, setCollection] = useState<PublishedCollection | null>(null)
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  useEffect(() => {
    let live = true
    const request = collectionId
      ? getPublishedCollection(collectionId).then((data) => {
          if (live) setCollection(data.collection)
        })
      : discoverCollections(page).then((data) => {
          if (live) {
            setCollections(data.collections)
            setHasMore(data.hasMore)
          }
        })
    void request
      .catch((e) => {
        if (live) setError(errorText(e))
      })
      .finally(() => {
        if (live) setLoading(false)
      })
    return () => {
      live = false
    }
  }, [collectionId, page])
  return (
    <DesignPage
      frame="165:71"
      title={
        collectionId
          ? (collection?.name ?? t('Collection partagée', 'Shared collection'))
          : t('Collections de la communauté', 'Community collections')
      }
      subtitle={
        collection
          ? `@${collection.owner ?? ''} · ${audienceLabel(collection.visibility)}`
          : t(
              'Découvrez les collections partagées avec vous.',
              'Discover collections shared with you.',
            )
      }
    >
      <Link className="design-link" to="/actualites">
        {t('Voir le fil d’actualité', 'See the feed')}
      </Link>
      {collection?.owner && (
        <Link
          className="design-link"
          to={`/communaute/profils/${encodeURIComponent(collection.owner)}`}
        >
          @{collection.owner}
        </Link>
      )}
      <ErrorMessage error={error} />
      {loading && <p role="status">{t('Chargement…', 'Loading…')}</p>}
      {!loading && !error && !collectionId && (
        <>
          <div className="design-grid">
            {collections.map((entry) => (
              <DesignTile
                key={entry.id}
                title={entry.name}
                detail={`@${entry.owner ?? ''} · ${entry.items.length} · ${audienceLabel(entry.visibility)}`}
                icon={entry.cover ? <img src={entry.cover} alt="" /> : null}
                to={`/communaute/collections/${entry.id}`}
              />
            ))}
          </div>
          {!collections.length && (
            <p>
              {t(
                'Aucune collection partagée avec vous pour le moment.',
                'No collections have been shared with you yet.',
              )}
            </p>
          )}
          <div className="design-inline-links">
            {page > 1 && (
              <button
                disabled={loading}
                onClick={() => {
                  setLoading(true)
                  setError(null)
                  setPage(page - 1)
                }}
              >
                {t('Précédent', 'Previous')}
              </button>
            )}
            {hasMore && (
              <button
                disabled={loading}
                onClick={() => {
                  setLoading(true)
                  setError(null)
                  setPage(page + 1)
                }}
              >
                {t('Suivant', 'Next')}
              </button>
            )}
          </div>
        </>
      )}
      {!error && collection && (
        <>
          {collection.cover && (
            <img className="collection-public-cover" src={collection.cover} alt="" />
          )}
          <div className="design-grid">
            {collection.items.map((item, index) => (
              <DesignTile
                key={index}
                title={item.name}
                detail={collectionItemDetail(item)}
                icon={item.photoUrl ? <img src={item.photoUrl} alt="" /> : null}
              />
            ))}
          </div>
          {!collection.items.length && (
            <p>
              {t(
                'Cette collection ne contient aucun article visible.',
                'This collection has no visible items.',
              )}
            </p>
          )}
        </>
      )}
      <Link className="design-link" to={collectionId ? '/communaute/collections' : '/collections'}>
        {collectionId
          ? t('Explorer les collections', 'Explore collections')
          : t('Mes collections', 'My collections')}
      </Link>
    </DesignPage>
  )
}
