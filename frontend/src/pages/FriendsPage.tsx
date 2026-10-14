import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Check, Heart, UserMinus, UserPlus, X } from 'lucide-react'
import { ApiError } from '../api/client.ts'
import * as friendsApi from '../api/friends.ts'
import { useAuth } from '../auth/useAuth.ts'
import { getCopy } from '../i18n/copy.ts'
import type { FriendEntry, FriendsResult } from '../types/friends.ts'

function errorMessage(error: unknown, copy: ReturnType<typeof getCopy>): string {
  if (!(error instanceof ApiError)) return copy.unexpectedError

  switch (error.code) {
    case 'not_found':
      return copy.errors.notFound
    case 'conflict':
      return copy.errors.conflict
    case 'forbidden':
      return copy.errors.forbidden
    case 'validation_failed':
      return copy.errors.invalid
    case 'too_many_attempts':
      return copy.errors.rateLimited
    default:
      return copy.unexpectedError
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
      <span className="avatar" aria-hidden="true">
        {entry.user.firstName.charAt(0).toUpperCase()}
      </span>
      <span className="profile-meta">
        <span className="profile-name">{entry.user.firstName}</span>
        {entry.user.username !== null && <span className="friend-username">@{entry.user.username}</span>}
      </span>
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
  const copy = getCopy()
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
      setError(errorMessage(cause, copy))
    } finally {
      setLoading(false)
    }
  }, [copy])

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
        if (!cancelled) setError(errorMessage(cause, copy))
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [copy])

  async function onSend(event: FormEvent) {
    event.preventDefault()
    setSending(true)
    setError(null)
    setSuccess(null)
    try {
      await friendsApi.sendFriendRequest(username.trim())
      setUsername('')
      setSuccess(copy.friends.sent)
      await load()
    } catch (cause) {
      setError(errorMessage(cause, copy))
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
      setSuccess(copy.friends.followed)
      await load()
    } catch (cause) {
      setError(errorMessage(cause, copy))
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
      setError(errorMessage(cause, copy))
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
      setError(errorMessage(cause, copy))
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
      setError(errorMessage(cause, copy))
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
      setError(errorMessage(cause, copy))
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
          <h1>{copy.friends.title}</h1>
          <p className="auth-sub">{copy.friends.adultOnly}</p>
        </div>
      </div>
    )
  }

  return (
    <div className="auth-page friends-page">
      <div className="friends-heading">
        <span className="friends-mark" aria-hidden="true"><Heart size={22} /></span>
        <div>
          <p className="auth-tagline">{copy.friends.tagline}</p>
          <h1>{copy.friends.title}</h1>
        </div>
      </div>

      <section className="auth-card friends-card">
        <h2>{copy.friends.addTitle}</h2>
        <p className="auth-sub">{copy.friends.addDescription}</p>
        <form className="friend-form" onSubmit={(event) => void onSend(event)}>
          <label className="sr-only" htmlFor="friend-username">{copy.friends.usernameLabel}</label>
          <input
            id="friend-username"
            className="input"
            autoComplete="off"
            placeholder={copy.friends.usernamePlaceholder}
            value={username}
            onChange={(event) => setUsername(event.target.value)}
            minLength={3}
            maxLength={30}
            required
          />
          <button type="submit" className="btn btn-primary" disabled={sending}>
            <UserPlus size={18} aria-hidden="true" />
            {sending ? copy.friends.sending : copy.friends.send}
          </button>
          <button type="button" className="btn btn-secondary" disabled={sending} onClick={() => void onFollow()}>
            {sending ? copy.friends.sending : copy.friends.follow}
          </button>
        </form>
      </section>

      {error !== null && <p role="alert" className="form-error">{error}</p>}
      {success !== null && <p role="status" className="friend-success">{success}</p>}

      {loading ? (
        <p className="auth-sub">{copy.friends.loading}</p>
      ) : (
        <div className="friend-sections">
          <section className="auth-card friends-card">
            <h2>{copy.friends.incoming}</h2>
            <ul className="friend-list">
              {data.incoming.length > 0
                ? entries(data.incoming, (entry) => [
                    { label: copy.friends.accept, Icon: Check, onClick: () => void onAccept(entry) },
                    { label: copy.friends.decline, Icon: X, onClick: () => void onRemoveRequest(entry) },
                  ])
                : <li className="friend-empty">{copy.friends.noIncoming}</li>}
            </ul>
          </section>

          <section className="auth-card friends-card">
            <h2>{copy.friends.outgoing}</h2>
            <ul className="friend-list">
              {data.outgoing.length > 0
                ? entries(data.outgoing, (entry) => [
                    { label: copy.friends.cancel, Icon: X, onClick: () => void onRemoveRequest(entry) },
                  ])
                : <li className="friend-empty">{copy.friends.noOutgoing}</li>}
            </ul>
          </section>

          <section className="auth-card friends-card">
            <h2>{copy.friends.mine} <span className="friend-count">{data.friends.length}</span></h2>
            <ul className="friend-list">
              {data.friends.length > 0
                ? entries(data.friends, (entry) => [
                    { label: copy.friends.remove, Icon: X, onClick: () => void onRemoveFriend(entry) },
                  ])
                : <li className="friend-empty">{copy.friends.noFriends}</li>}
            </ul>
          </section>
          <section className="auth-card friends-card">
            <h2>{copy.friends.following} <span className="friend-count">{data.following.length}</span></h2>
            <ul className="friend-list">
              {data.following.length > 0
                ? entries(data.following, (entry) => [
                    { label: copy.friends.unfollow, Icon: UserMinus, onClick: () => void onUnfollow(entry) },
                  ])
                : <li className="friend-empty">{copy.friends.noFollowing}</li>}
            </ul>
          </section>
          <section className="auth-card friends-card">
            <h2>{copy.friends.followers} <span className="friend-count">{data.followers.length}</span></h2>
            <ul className="friend-list">
              {data.followers.length > 0
                ? entries(data.followers, () => [])
                : <li className="friend-empty">{copy.friends.noFollowers}</li>}
            </ul>
          </section>
        </div>
      )}
    </div>
  )
}
