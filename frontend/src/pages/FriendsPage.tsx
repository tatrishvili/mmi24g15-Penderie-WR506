import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { Check, Heart, UserMinus, UserPlus, X } from 'lucide-react'
import { ApiError } from '../api/client.ts'
import * as friendsApi from '../api/friends.ts'
import { useAuth } from '../auth/useAuth.ts'
import { t } from '../i18n/copy.ts'
import type { FriendEntry, FriendsResult } from '../types/friends.ts'

function errorMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('unexpectedError')

  switch (error.code) {
    case 'not_found':
      return t('errors.notFound')
    case 'conflict':
      return t('errors.conflict')
    case 'forbidden':
      return t('errors.forbidden')
    case 'validation_failed':
      return t('errors.invalid')
    case 'too_many_attempts':
      return t('errors.rateLimited')
    default:
      return t('unexpectedError')
  }
}

function FriendRow({
  entry,
  actions,
}: {
  entry: FriendEntry
  actions: { label: string; Icon: typeof Check; onClick: () => void; disabled?: boolean }[]
}) {
  return (
    <li className="friend-row">
      <Link
        className="friend-profile-link"
        to={entry.user.username ? `/communaute/profils/${encodeURIComponent(entry.user.username)}` : '/amis'}
      >
        <span className="avatar" aria-hidden="true">
          {entry.user.photoUrl ? <img src={entry.user.photoUrl} alt="" /> : (entry.user.displayName ?? entry.user.firstName).charAt(0).toUpperCase()}
        </span>
        <span className="profile-meta">
          <span className="profile-name">{entry.user.displayName ?? entry.user.firstName}</span>
          {entry.user.username !== null && <span className="friend-username">@{entry.user.username}</span>}
        </span>
      </Link>
      {actions.map((action) => (
        <button
          key={action.label}
          type="button"
          className="friend-action"
          onClick={action.onClick}
          disabled={action.disabled}
          aria-label={action.label}
          title={action.label}
        >
          <action.Icon size={18} aria-hidden="true" />
        </button>
      ))}
    </li>
  )
}

function emptyResult(): FriendsResult {
  return { friends: [], incoming: [], outgoing: [], followers: [], following: [] }
}

export default function FriendsPage() {
  const { user } = useAuth()
  const [search] = useSearchParams()
  const list = search.get('liste')
  const filter = ['amis', 'abonnes', 'abonnements', 'demandes'].includes(list ?? '') ? list : null
  const [data, setData] = useState<FriendsResult>(emptyResult)
  const [username, setUsername] = useState('')
  const [loading, setLoading] = useState(true)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [sending, setSending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const load = useCallback(async () => {
    try {
      setData(await friendsApi.listFriends())
      setError(null)
    } catch (cause) {
      setError(errorMessage(cause as unknown as Error))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    let cancelled = false
    void friendsApi.listFriends()
      .then((result) => {
        if (!cancelled) {
          setData(result)
          setError(null)
        }
      })
      .catch((cause: unknown) => {
        if (!cancelled) setError(errorMessage(cause))
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [])

  async function onSend(event: FormEvent) {
    event.preventDefault()
    setSending(true)
    setError(null)
    setSuccess(null)
    try {
      await friendsApi.sendFriendRequest(username.trim())
      setUsername('')
      setSuccess(t('friends.sent'))
      await load()
    } catch (cause) {
      setError(errorMessage(cause as unknown as Error))
    } finally {
      setSending(false)
    }
  }

  async function onFollow() {
    setSending(true)
    setError(null)
    setSuccess(null)
    try {
      await friendsApi.follow(username.trim())
      setUsername('')
      setSuccess(t('friends.followed'))
      await load()
    } catch (cause) {
      setError(errorMessage(cause as unknown as Error))
    } finally {
      setSending(false)
    }
  }

  async function onAccept(entry: FriendEntry) {
    setBusyId(entry.id)
    setError(null)
    try {
      await friendsApi.acceptFriendRequest(entry.id)
      await load()
    } catch (cause) {
      setError(errorMessage(cause as unknown as Error))
    } finally {
      setBusyId(null)
    }
  }

  async function onRemoveRequest(entry: FriendEntry) {
    setBusyId(entry.id)
    setError(null)
    try {
      await friendsApi.removeFriendRequest(entry.id)
      await load()
    } catch (cause) {
      setError(errorMessage(cause as unknown as Error))
    } finally {
      setBusyId(null)
    }
  }

  async function onRemoveFriend(entry: FriendEntry) {
    setBusyId(entry.id)
    setError(null)
    try {
      await friendsApi.removeFriend(entry.id)
      await load()
    } catch (cause) {
      setError(errorMessage(cause as unknown as Error))
    } finally {
      setBusyId(null)
    }
  }

  async function onUnfollow(entry: FriendEntry) {
    setBusyId(entry.id)
    setError(null)
    try {
      await friendsApi.unfollow(entry.id)
      await load()
    } catch (cause) {
      setError(errorMessage(cause as unknown as Error))
    } finally {
      setBusyId(null)
    }
  }

  function entries(
    list: FriendEntry[],
    getActions: (entry: FriendEntry) => { label: string; Icon: typeof Check; onClick: () => void }[],
  ) {
    return list.map((entry) => (
      <FriendRow
        key={entry.id}
        entry={entry}
        actions={getActions(entry).map((action) => ({ ...action, disabled: busyId !== null }))}
      />
    ))
  }

  if (user === null) {
    return (
      <div className="auth-page">
        <div className="auth-card">
          <h1>{t(filter === 'abonnes' ? 'friends.followers' : filter === 'abonnements' ? 'friends.following' : filter === 'demandes' ? 'friends.title' : 'friends.title')}</h1>
          <p className="auth-sub">{t('friends.adultOnly')}</p>
        </div>
      </div>
    )
  }

  return (
    <div className="auth-page friends-page">
      <div className="friends-heading">
        <span className="friends-mark" aria-hidden="true"><Heart size={22} /></span>
        <div>
          <p className="auth-tagline">{t('friends.tagline')}</p>
          <h1>{filter === 'abonnes' ? t('friends.followers') : filter === 'abonnements' ? t('friends.following') : filter === 'demandes' ? t('friends.title') : t('friends.title')}</h1>
        </div>
      </div>

      <Link className="btn btn-secondary" to="/actualites">
        {t('friends.seeTheFeed')}
      </Link>
      <nav className="friend-filters" aria-label={t('friends.connections')}>
        <Link to="/profil">{t('friends.myProfile')}</Link>
        {[[null, t('friends.manage')], ['amis', t('friends.friendsLabel')], ['abonnes', t('friends.followers')], ['abonnements', t('friends.following')], ['demandes', t('friends.requests')]].map(([value, label]) => <Link key={value ?? 'all'} aria-current={filter === value ? 'page' : undefined} to={value ? `/amis?liste=${value}` : '/amis'}>{label}</Link>)}
      </nav>
      <section hidden={filter !== null} className="auth-card friends-card">
        <h2>{t('friends.addTitle')}</h2>
        <p className="auth-sub">{t('friends.addDescription')}</p>
        <form className="friend-form" onSubmit={(event) => void onSend(event)}>
          <label className="sr-only" htmlFor="friend-username">{t('friends.usernameLabel')}</label>
          <input
            id="friend-username"
            className="input"
            autoComplete="off"
            placeholder={t('friends.usernamePlaceholder')}
            value={username}
            onChange={(event) => setUsername(event.target.value)}
            minLength={3}
            maxLength={30}
            required
          />
          <button type="submit" className="btn btn-primary" disabled={sending}>
            <UserPlus size={18} aria-hidden="true" />
            {sending ? t('friends.sending') : t('friends.send')}
          </button>
          <button type="button" className="btn btn-secondary" disabled={sending} onClick={() => void onFollow()}>
            {sending ? t('friends.sending') : t('friends.follow')}
          </button>
        </form>
      </section>

      {error !== null && <p role="alert" className="form-error">{error}</p>}
      {success !== null && <p role="status" className="friend-success">{success}</p>}

      {loading ? (
        <p className="auth-sub">{t('friends.loading')}</p>
      ) : (
        <div className="friend-sections">
          <section hidden={filter !== null && filter !== 'demandes'} className="auth-card friends-card">
            <h2>{t('friends.incoming')}</h2>
            <ul className="friend-list">
              {data.incoming.length > 0
                ? entries(data.incoming, (entry) => [
                    { label: t('friends.accept'), Icon: Check, onClick: () => void onAccept(entry) },
                    { label: t('friends.decline'), Icon: X, onClick: () => void onRemoveRequest(entry) },
                  ])
                : <li className="friend-empty">{t('friends.noIncoming')}</li>}
            </ul>
          </section>

          <section hidden={filter !== null && filter !== 'demandes'} className="auth-card friends-card">
            <h2>{t('friends.outgoing')}</h2>
            <ul className="friend-list">
              {data.outgoing.length > 0
                ? entries(data.outgoing, (entry) => [
                    { label: t('friends.cancel'), Icon: X, onClick: () => void onRemoveRequest(entry) },
                  ])
                : <li className="friend-empty">{t('friends.noOutgoing')}</li>}
            </ul>
          </section>

          <section hidden={filter !== null && filter !== 'amis'} className="auth-card friends-card">
            <h2>{t('friends.mine')} <span className="friend-count">{data.friends.length}</span></h2>
            <ul className="friend-list">
              {data.friends.length > 0
                ? entries(data.friends, (entry) => [
                    { label: t('friends.remove'), Icon: X, onClick: () => void onRemoveFriend(entry) },
                  ])
                : <li className="friend-empty">{t('friends.noFriends')}</li>}
            </ul>
          </section>

          <section hidden={filter !== null && filter !== 'abonnements'} className="auth-card friends-card">
            <h2>{t('friends.following')} <span className="friend-count">{data.following.length}</span></h2>
            <ul className="friend-list">
              {data.following.length > 0
                ? entries(data.following, (entry) => [
                    { label: t('friends.unfollow'), Icon: UserMinus, onClick: () => void onUnfollow(entry) },
                  ])
                : <li className="friend-empty">{t('friends.noFollowing')}</li>}
            </ul>
          </section>

          <section hidden={filter !== null && filter !== 'abonnes'} className="auth-card friends-card">
            <h2>{t('friends.followers')} <span className="friend-count">{data.followers.length}</span></h2>
            <ul className="friend-list">
              {data.followers.length > 0
                ? entries(data.followers, () => [])
                : <li className="friend-empty">{t('friends.noFollowers')}</li>}
            </ul>
          </section>
        </div>
      )}
    </div>
  )
}