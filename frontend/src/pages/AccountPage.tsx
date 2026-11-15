import { useState } from 'react'
import type { FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { LogOut, ScanLine, Shirt, UsersRound } from 'lucide-react'
import * as authApi from '../api/auth.ts'
import { ApiError } from '../api/client.ts'
import { useAuth } from '../auth/useAuth.ts'
import { t } from '../i18n/copy.ts'
import { roleLabel } from '../types/auth.ts'
import { itemText as tr } from '../lib/itemCatalog.ts'

/**
 * "Mon compte" — every info about me in one place: account, foyer,
 * active profile, household members and places, plus shortcuts to
 * the scanner, the dressing and the profiles.
 */
export default function AccountPage() {
  const { user, household, profiles, places, activeProfile, logout, refresh } = useAuth()
  const [username, setUsername] = useState('')
  const [savingUsername, setSavingUsername] = useState(false)
  const [usernameError, setUsernameError] = useState<string | null>(null)
  const [usernameSuccess, setUsernameSuccess] = useState<string | null>(null)

  const defaultPlace = places.find((place) => place.isDefault) ?? places[0] ?? null

  async function onSetUsername(event: FormEvent) {
    event.preventDefault()
    setSavingUsername(true)
    setUsernameError(null)
    setUsernameSuccess(null)
    try {
      await authApi.setUsername(username)
      await refresh()
      setUsernameSuccess(t('account.usernameSaved'))
    } catch (error) {
      if (error instanceof ApiError && error.code === 'conflict') {
        setUsernameError(t('account.usernameTaken'))
      } else if (error instanceof ApiError && error.code === 'validation_failed') {
        setUsernameError(t('errors.invalid'))
      } else {
        setUsernameError(t('account.usernameError'))
      }
    } finally {
      setSavingUsername(false)
    }
  }

  return (
    <div className="auth-page">
      <p className="auth-tagline">
        {household === null ? (
          tr('Votre compte est prêt.', 'Your account is ready.')
        ) : (
          <>
            {tr('Foyer', 'Household')} « {household.name} » — {tr('code', 'code')} : <strong>{household.slug}</strong>
          </>
        )}
      </p>

      <div className="auth-card">
        <h1>{tr('Mon compte', 'My account')}</h1>

        <section aria-label={tr('Mon compte utilisateur', 'My user account')}>
          <h2 className="section-title">{t('account.usernameTitle')}</h2>
          {user?.username === undefined || user.username === null ? (
            <form className="auth-form" onSubmit={(event) => void onSetUsername(event)}>
              <p className="auth-sub">{t('account.usernameDescription')}</p>
              <label htmlFor="account-username">{t('account.usernameLabel')}</label>
              <input
                id="account-username"
                className="input"
                autoComplete="username"
                minLength={3}
                maxLength={30}
                pattern="[A-Za-z0-9_]{3,30}"
                placeholder={t('account.usernamePlaceholder')}
                required
                value={username}
                onChange={(event) => setUsername(event.target.value)}
              />
              <button type="submit" className="btn btn-primary" disabled={savingUsername}>
                {savingUsername ? t('account.usernameSaving') : t('account.usernameSave')}
              </button>
              {usernameError !== null && <p role="alert" className="form-error">{usernameError}</p>}
            </form>
          ) : (
            <p className="auth-sub">@{user.username}</p>
          )}
          {usernameSuccess !== null && <p role="status" className="friend-success">{usernameSuccess}</p>}
        </section>

        <section aria-label={tr('Mon e-mail', 'My e-mail')}>
          <h2 className="section-title">{tr('Connexion', 'Sign-in')}</h2>
          <p className="auth-sub">{user?.email ?? '—'}</p>
        </section>

        <section aria-label={tr('Mon profil actif', 'My active profile')}>
          <h2 className="section-title">{tr('Profil actif', 'Active profile')}</h2>
          {activeProfile === null ? (
            <p className="auth-sub">{tr('Aucun profil actif pour le moment.', 'No active profile yet.')}</p>
          ) : (
            <p className="auth-sub">
              <strong>{activeProfile.name}</strong> — {roleLabel(activeProfile.role)}
            </p>
          )}
        </section>

        <section aria-label={tr('Mon foyer', 'My household')}>
          <h2 className="section-title">{tr('Foyer', 'Household')}</h2>
          {household === null ? (
            <p className="auth-sub">
              {tr('Pas encore de foyer.', 'No household yet.')} <Link to="/profils">{tr('Créez votre foyer', 'Create your household')}</Link> {tr('pour commencer.', 'to get started.')}
            </p>
          ) : (
            <>
              <p className="auth-sub">
                <strong>{household.name}</strong> — {tr('lieu principal', 'main place')} :{' '}
                {defaultPlace?.name ?? '—'}
              </p>
              <p className="auth-sub">
                {profiles.length} {tr('profils', 'profiles')} · {places.length} {tr('lieux', 'places')}
              </p>
            </>
          )}
        </section>

        {profiles.length > 0 && (
          <section aria-label={tr('Membres du foyer', 'Household members')}>
            <h2 className="section-title">{tr('Membres', 'Members')}</h2>
            <ul className="profile-list">
              {profiles.map((profile) => (
                <li key={profile.id} className="profile-row">
                  <span className="avatar" aria-hidden="true">
                    {profile.name.charAt(0).toUpperCase()}
                  </span>
                  <span className="profile-meta">
                    <span className="profile-name">{profile.name}</span>
                    <br />
                    <span className="profile-role">
                      {roleLabel(profile.role)}
                      {profile.hasPin ? ` · ${tr('code PIN', 'PIN')}` : ''}
                    </span>
                  </span>
                </li>
              ))}
            </ul>
          </section>
        )}

        <div className="account-actions">
          <Link className="btn btn-primary" to="/scanner">
            <ScanLine size={18} aria-hidden="true" /> {tr('Scanner un article', 'Scan an item')}
          </Link>
          <Link className="btn btn-secondary" to="/dressing">
            <Shirt size={18} aria-hidden="true" /> {tr('Mon dressing', 'My wardrobe')}
          </Link>
          <Link className="btn btn-secondary" to="/profils">
            <UsersRound size={18} aria-hidden="true" /> {tr('Gérer les profils', 'Manage profiles')}
          </Link>
          <button type="button" className="btn btn-secondary" onClick={() => void logout()}>
            <LogOut size={18} aria-hidden="true" /> {tr('Se déconnecter', 'Sign out')}
          </button>
        </div>
      </div>
    </div>
  )
}