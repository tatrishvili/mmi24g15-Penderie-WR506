import { useEffect, useRef, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { Heart, UsersRound, Shirt } from 'lucide-react'
import { discoverCollections, getCommunityProfile } from '../api/social.ts'
import type { CommunityProfile, PublishedCollection } from '../api/social.ts'
import { DesignPage, ErrorMessage } from '../components/DesignPage.tsx'
import { audienceLabel, collectionItemDetail } from '../lib/collectionAudience.ts'
import { itemText as t } from '../lib/itemCatalog.ts'
import { errorText } from '../lib/errors.ts'

type Scope = 'following' | 'community'

function PublicationCard({ collection }: { collection: PublishedCollection }) {
  const destination = `/communaute/collections/${collection.id}`
  return (
    <article className="feed-card" aria-label={collection.name}>
      <header className="feed-card__author">
        <span className="feed-avatar" aria-hidden="true">
          {collection.ownerPhotoUrl ? (
            <img src={collection.ownerPhotoUrl} alt="" />
          ) : (
            ((collection.ownerDisplayName ?? collection.owner)?.charAt(0).toUpperCase() ?? 'P')
          )}
        </span>
        <div>
          {collection.owner && (
            <Link to={`/communaute/profils/${encodeURIComponent(collection.owner)}`}>
              <strong>{collection.ownerDisplayName ?? collection.owner}</strong>
              <small>@{collection.owner}</small>
            </Link>
          )}
          <small>{audienceLabel(collection.visibility)}</small>
        </div>
      </header>
      {collection.cover && (
        <Link to={destination} tabIndex={-1} aria-hidden="true">
          <img className="feed-card__cover" src={collection.cover} alt="" loading="lazy" />
        </Link>
      )}
      <div className="feed-card__body">
        <h2>
          <Link to={destination}>{collection.name}</Link>
        </h2>
        <p className="feed-card__count">
          {collection.items.length}{' '}
          {collection.items.length === 1
            ? t('article partagé', 'shared item')
            : t('articles partagés', 'shared items')}
        </p>
        {collection.items.length > 0 && (
          <ul className="feed-items">
            {collection.items.slice(0, 4).map((item, index) => (
              <li key={index}>
                {item.photoUrl ? (
                  <img src={item.photoUrl} alt={item.name} loading="lazy" />
                ) : (
                  <span className="feed-item-placeholder" aria-hidden="true">
                    <Shirt size={30} />
                  </span>
                )}
                <strong>{item.name}</strong>
                {collectionItemDetail(item) && <small>{collectionItemDetail(item)}</small>}
              </li>
            ))}
          </ul>
        )}
        <Link className="btn btn-secondary" to={destination}>
          {t('Voir la collection', 'View collection')}
        </Link>
      </div>
    </article>
  )
}

function Feed({ username, scope }: { username?: string; scope: Scope }) {
  const [collections, setCollections] = useState<PublishedCollection[]>([])
  const [profile, setProfile] = useState<CommunityProfile | null>(null)
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [revision, setRevision] = useState(0)
  const sentinel = useRef<HTMLDivElement>(null)

  useEffect(() => {
    let live = true
    void Promise.all([
      discoverCollections(page, username ? { owner: username } : { scope }),
      username ? getCommunityProfile(username) : Promise.resolve(null),
    ])
      .then(([result, person]) => {
        if (!live) return
        setCollections((previous) => {
          const combined = page === 1 ? result.collections : [...previous, ...result.collections]
          return [...new Map(combined.map((entry) => [entry.id, entry])).values()]
        })
        setHasMore(result.hasMore)
        setProfile(person?.profile ?? null)
      })
      .catch((cause) => {
        if (live) setError(errorText(cause))
      })
      .finally(() => {
        if (live) setLoading(false)
      })
    return () => {
      live = false
    }
  }, [username, scope, page, revision])

  useEffect(() => {
    const refresh = () => {
      setCollections([])
      setPage(1)
      setLoading(true)
      setError(null)
      setRevision((value) => value + 1)
    }
    window.addEventListener('focus', refresh)
    return () => window.removeEventListener('focus', refresh)
  }, [])

  useEffect(() => {
    if (
      loading ||
      error ||
      !hasMore ||
      !sentinel.current ||
      typeof IntersectionObserver === 'undefined'
    )
      return
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          observer.disconnect()
          setLoading(true)
          setPage((value) => value + 1)
        }
      },
      { rootMargin: '240px' },
    )
    observer.observe(sentinel.current)
    return () => observer.disconnect()
  }, [loading, error, hasMore])

  return (
    <>
      {profile && (
        <header className="community-profile">
          <span className="feed-avatar feed-avatar--large" aria-hidden="true">
            {profile.photoUrl ? (
              <img src={profile.photoUrl} alt="" />
            ) : (
              (profile.displayName ?? profile.username).charAt(0).toUpperCase()
            )}
          </span>
          <h2>{profile.displayName ?? profile.username}</h2>
          <p className="community-profile__handle">@{profile.username}</p>
          <span className="community-profile__relationship">
            {profile.self
              ? t('Mon profil', 'My profile')
              : profile.friend
                ? t('Amis', 'Friends')
                : profile.following
                  ? t('Abonné(e)', 'Following')
                  : t('Membre Penderie', 'Penderie member')}
          </span>
          <p>{t('Ses collections partagées avec vous', 'Collections shared with you')}</p>
        </header>
      )}
      <div className="social-feed" aria-busy={loading}>
        {collections.map((collection) => (
          <PublicationCard key={collection.id} collection={collection} />
        ))}
      </div>
      {!loading && !error && collections.length === 0 && (
        <div className="feed-empty">
          <Heart size={36} aria-hidden="true" />
          <h2>{t('Pas encore de partage', 'No shares yet')}</h2>
          <p>
            {username
              ? t(
                  'Cette personne n’a aucune collection partagée avec vous pour le moment.',
                  'This person has no collections shared with you yet.',
                )
              : scope === 'following'
                ? t(
                    'Les collections de vos amis et des personnes que vous suivez apparaîtront ici.',
                    'Collections from friends and people you follow will appear here.',
                  )
                : t(
                    'Les collections partagées avec la communauté apparaîtront ici.',
                    'Collections shared with the community will appear here.',
                  )}
          </p>
          <Link className="btn btn-secondary" to="/amis">
            {t('Retrouver mes amis', 'Find my friends')}
          </Link>
        </div>
      )}
      <ErrorMessage error={error} />
      {error && (
        <button
          className="btn btn-secondary"
          onClick={() => {
            setError(null)
            setLoading(true)
            setRevision((value) => value + 1)
          }}
        >
          {t('Réessayer', 'Try again')}
        </button>
      )}
      {loading && <p role="status">{t('Chargement des partages…', 'Loading shares…')}</p>}
      <div ref={sentinel}>
        {hasMore && !error && (
          <button
            className="btn btn-secondary"
            disabled={loading}
            onClick={() => {
              setLoading(true)
              setPage((value) => value + 1)
            }}
          >
            {t('Voir plus de partages', 'Load more shares')}
          </button>
        )}
      </div>
    </>
  )
}

export default function SocialFeedPage() {
  const { username } = useParams()
  const [scope, setScope] = useState<Scope>('following')
  return (
    <DesignPage
      frame="165:71"
      className="social-feed-page"
      title={
        username
          ? t('Profil Penderie', 'Penderie profile')
          : t('Le fil Penderie', 'The Penderie feed')
      }
      subtitle={
        username
          ? undefined
          : t('De jolies découvertes, partagées avec vous.', 'Lovely discoveries, shared with you.')
      }
    >
      <nav className="feed-navigation" aria-label={t('Espace communauté', 'Community navigation')}>
        <Link to="/actualites">{t('Le fil', 'Feed')}</Link>
        <Link to="/amis">
          <UsersRound size={18} aria-hidden="true" />
          {t('Mes amis', 'My friends')}
        </Link>
      </nav>
      {!username && (
        <div
          className="feed-tabs"
          role="group"
          aria-label={t('Filtrer les partages', 'Filter shares')}
        >
          <button aria-pressed={scope === 'following'} onClick={() => setScope('following')}>
            {t('Mes amis & abonnements', 'Friends & following')}
          </button>
          <button aria-pressed={scope === 'community'} onClick={() => setScope('community')}>
            {t('Découvrir', 'Discover')}
          </button>
        </div>
      )}
      <Feed key={`${username ?? ''}:${scope}`} username={username} scope={scope} />
    </DesignPage>
  )
}
