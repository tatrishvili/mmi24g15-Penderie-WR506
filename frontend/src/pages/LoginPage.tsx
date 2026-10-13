import { useState } from 'react'
import type { FormEvent } from 'react'
import { Link, Navigate } from 'react-router-dom'
import { Check } from 'lucide-react'
import { householdProfiles } from '../api/auth.ts'
import { ApiError } from '../api/client.ts'
import { useAuth } from '../auth/useAuth.ts'
import type { ProfileSummary } from '../types/auth.ts'
import { roleLabel } from '../types/auth.ts'

type Mode = 'password' | 'code'

export default function LoginPage() {
  const { status, login, pinLogin } = useAuth()
  const [mode, setMode] = useState<Mode>('password')

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loginError, setLoginError] = useState<string | null>(null)
  const [loggingIn, setLoggingIn] = useState(false)

  const [slug, setSlug] = useState('')
  const [householdName, setHouseholdName] = useState<string | null>(null)
  const [candidates, setCandidates] = useState<ProfileSummary[]>([])
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [pin, setPin] = useState('')
  const [codeError, setCodeError] = useState<string | null>(null)
  const [lookingUp, setLookingUp] = useState(false)
  const [entering, setEntering] = useState(false)

  if (status === 'authenticated') {
    return <Navigate to="/profils" replace />
  }

  async function onPasswordSubmit(event: FormEvent) {
    event.preventDefault()
    setLoginError(null)
    setLoggingIn(true)

    try {
      await login(email.trim(), password)
    } catch (error) {
      setLoginError(
        error instanceof ApiError ? error.message : 'Une erreur inattendue est survenue.',
      )
    } finally {
      setLoggingIn(false)
    }
  }

  async function onSlugSubmit(event: FormEvent) {
    event.preventDefault()
    setCodeError(null)
    setCandidates([])
    setSelectedId(null)
    setHouseholdName(null)
    setLookingUp(true)

    try {
      const result = await householdProfiles(slug.trim().toLowerCase())
      setHouseholdName(result.household.name)
      setCandidates(result.profiles)
    } catch (error) {
      setCodeError(
        error instanceof ApiError && error.status === 404
          ? 'Foyer introuvable. Vérifiez le code de votre foyer.'
          : error instanceof ApiError
            ? error.message
            : 'Une erreur inattendue est survenue.',
      )
    } finally {
      setLookingUp(false)
    }
  }

  async function onPinSubmit(event: FormEvent) {
    event.preventDefault()

    if (selectedId === null) {
      setCodeError('Choisissez un profil.')
      return
    }

    setCodeError(null)
    setEntering(true)

    try {
      await pinLogin(slug.trim().toLowerCase(), selectedId, pin)
    } catch (error) {
      setCodeError(
        error instanceof ApiError ? error.message : 'Une erreur inattendue est survenue.',
      )
    } finally {
      setEntering(false)
    }
  }

  return (
    <div className="auth-page">
      <span className="auth-brand">Penderie</span>
      <p className="auth-tagline">Bon retour parmi nous.</p>

      <div className="auth-card">
        <h1>Se connecter</h1>

        <div className="steps" role="tablist" aria-label="Méthode de connexion">
          <button
            type="button"
            role="tab"
            aria-selected={mode === 'password'}
            className={mode === 'password' ? 'step step--current' : 'step'}
            onClick={() => setMode('password')}
          >
            E-mail
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={mode === 'code'}
            className={mode === 'code' ? 'step step--current' : 'step'}
            onClick={() => setMode('code')}
          >
            Entrer avec un code
          </button>
        </div>

        {mode === 'password' ? (
          <form className="auth-form" onSubmit={onPasswordSubmit} noValidate>
            {loginError !== null && (
              <p role="alert" className="form-error">
                {loginError}
              </p>
            )}

            <div className="field">
              <label htmlFor="login-email">E-mail</label>
              <input
                id="login-email"
                className="input"
                type="email"
                autoComplete="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                required
              />
            </div>

            <div className="field">
              <label htmlFor="login-password">Mot de passe</label>
              <input
                id="login-password"
                className="input"
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                required
              />
            </div>

            <button
              type="submit"
              className="btn btn-primary"
              disabled={loggingIn || status === 'loading'}
            >
              {loggingIn ? 'Connexion…' : 'Se connecter'}
            </button>
          </form>
        ) : (
          <div className="auth-form">
            {codeError !== null && (
              <p role="alert" className="form-error">
                {codeError}
              </p>
            )}

            <form onSubmit={onSlugSubmit} noValidate>
              <div className="field">
                <label htmlFor="code-slug">Code du foyer</label>
                <input
                  id="code-slug"
                  className="input"
                  type="text"
                  placeholder="famille-moreau"
                  autoComplete="off"
                  value={slug}
                  onChange={(event) => setSlug(event.target.value)}
                  required
                />
              </div>
              <div style={{ marginTop: 12 }}>
                <button type="submit" className="btn btn-secondary" disabled={lookingUp}>
                  {lookingUp ? 'Recherche…' : 'Trouver mon foyer'}
                </button>
              </div>
            </form>

            {candidates.length > 0 && (
              <form onSubmit={onPinSubmit} noValidate>
                <p className="auth-sub">Foyer « {householdName} » — qui entre ?</p>
                <ul className="profile-list">
                  {candidates.map((profile) => (
                    <li key={profile.id}>
                      <button
                        type="button"
                        className={
                          selectedId === profile.id
                            ? 'profile-row profile-row--active'
                            : 'profile-row'
                        }
                        onClick={() => setSelectedId(profile.id)}
                        aria-pressed={selectedId === profile.id}
                      >
                        <span className="avatar" aria-hidden="true">
                          {profile.name.charAt(0).toUpperCase()}
                        </span>
                        <span className="profile-meta">
                          <span className="profile-name">{profile.name}</span>
                          <br />
                          <span className="profile-role">{roleLabel(profile.role)}</span>
                        </span>
                        {selectedId === profile.id && (
                          <Check className="profile-check" size={20} aria-label="Sélectionné" />
                        )}
                      </button>
                    </li>
                  ))}
                </ul>

                <div className="pin-pad">
                  <div className="field">
                    <label htmlFor="code-pin">Code PIN (4 à 6 chiffres)</label>
                    <input
                      id="code-pin"
                      className="input input--pin"
                      type="password"
                      inputMode="numeric"
                      pattern="[0-9]*"
                      autoComplete="off"
                      maxLength={6}
                      value={pin}
                      onChange={(event) => setPin(event.target.value.replace(/[^0-9]/g, ''))}
                      required
                    />
                  </div>
                  <button
                    type="submit"
                    className="btn btn-primary"
                    disabled={entering || selectedId === null}
                  >
                    {entering ? 'Vérification…' : 'Entrer'}
                  </button>
                </div>
              </form>
            )}
          </div>
        )}

        <p className="auth-switch">
          Pas encore de foyer ? <Link to="/inscription">Créer un compte</Link>
        </p>
      </div>
    </div>
  )
}
