import { useState } from 'react'
import type { FormEvent } from 'react'
import { Link, Navigate } from 'react-router-dom'
import { ApiError } from '../api/client.ts'
import { useAuth } from '../auth/useAuth.ts'

function fieldMessage(violations: Record<string, string>, keys: string[]): string | null {
  for (const key of keys) {
    if (violations[key] !== undefined) return violations[key]
  }
  return null
}

export default function RegisterPage() {
  const { status, register } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirm, setPasswordConfirm] = useState('')
  const [householdName, setHouseholdName] = useState('')
  const [firstName, setFirstName] = useState('')
  const [placeName, setPlaceName] = useState('')
  const [violations, setViolations] = useState<Record<string, string>>({})
  const [globalError, setGlobalError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  if (status === 'authenticated') {
    return <Navigate to="/profils" replace />
  }

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setViolations({})
    setGlobalError(null)

    if (password !== passwordConfirm) {
      setViolations({ passwordConfirm: 'La confirmation ne correspond pas au mot de passe.' })
      return
    }

    setSubmitting(true)

    try {
      await register({
        email: email.trim(),
        password,
        passwordConfirm,
        householdName: householdName.trim(),
        firstName: firstName.trim(),
        placeName: placeName.trim() === '' ? undefined : placeName.trim(),
      })
    } catch (error) {
      if (error instanceof ApiError) {
        if (Object.keys(error.violations).length > 0) {
          setViolations(error.violations)
        } else {
          setGlobalError(error.message)
        }
      } else {
        setGlobalError('Une erreur inattendue est survenue.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="auth-page">
      <span className="auth-brand">Penderie</span>
      <p className="auth-tagline">Tout ce que vous possédez, au même endroit.</p>

      <div className="auth-card">
        <h1>Créer mon foyer</h1>
        <p className="auth-sub">Un compte parent, un foyer, une première maison.</p>

        <form className="auth-form" onSubmit={onSubmit} noValidate>
          {globalError !== null && (
            <p role="alert" className="form-error">
              {globalError}
            </p>
          )}

          <div className="field">
            <label htmlFor="register-email">E-mail</label>
            <input
              id="register-email"
              className="input"
              type="email"
              autoComplete="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              required
            />
            {fieldMessage(violations, ['email']) !== null && (
              <span className="field-error">{fieldMessage(violations, ['email'])}</span>
            )}
          </div>

          <div className="field">
            <label htmlFor="register-password">Mot de passe (8 caractères minimum)</label>
            <input
              id="register-password"
              className="input"
              type="password"
              autoComplete="new-password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              required
              minLength={8}
            />
            {fieldMessage(violations, ['password']) !== null && (
              <span className="field-error">{fieldMessage(violations, ['password'])}</span>
            )}
          </div>

          <div className="field">
            <label htmlFor="register-password-confirm">Confirmer le mot de passe</label>
            <input
              id="register-password-confirm"
              className="input"
              type="password"
              autoComplete="new-password"
              value={passwordConfirm}
              onChange={(event) => setPasswordConfirm(event.target.value)}
              required
            />
            {fieldMessage(violations, ['passwordConfirm']) !== null && (
              <span className="field-error">{fieldMessage(violations, ['passwordConfirm'])}</span>
            )}
          </div>

          <div className="field">
            <label htmlFor="register-household">Nom du foyer</label>
            <input
              id="register-household"
              className="input"
              type="text"
              placeholder="Famille Moreau"
              value={householdName}
              onChange={(event) => setHouseholdName(event.target.value)}
              required
            />
            {fieldMessage(violations, ['householdName']) !== null && (
              <span className="field-error">{fieldMessage(violations, ['householdName'])}</span>
            )}
          </div>

          <div className="field">
            <label htmlFor="register-firstname">Votre prénom</label>
            <input
              id="register-firstname"
              className="input"
              type="text"
              autoComplete="given-name"
              value={firstName}
              onChange={(event) => setFirstName(event.target.value)}
              required
            />
            {fieldMessage(violations, ['firstName']) !== null && (
              <span className="field-error">{fieldMessage(violations, ['firstName'])}</span>
            )}
          </div>

          <div className="field">
            <label htmlFor="register-place">Première maison (optionnel)</label>
            <input
              id="register-place"
              className="input"
              type="text"
              placeholder="Maison"
              value={placeName}
              onChange={(event) => setPlaceName(event.target.value)}
            />
          </div>

          <button
            type="submit"
            className="btn btn-primary"
            disabled={submitting || status === 'loading'}
          >
            {submitting ? 'Création…' : 'Créer mon foyer'}
          </button>
        </form>

        <p className="auth-switch">
          Déjà un compte ? <Link to="/connexion">Se connecter</Link>
        </p>
      </div>
    </div>
  )
}
