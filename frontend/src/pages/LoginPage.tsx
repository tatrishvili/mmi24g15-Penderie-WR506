import { useState } from 'react'
import type { FormEvent } from 'react'
import { Link, Navigate } from 'react-router-dom'
import { ApiError } from '../api/client.ts'
import { useAuth } from '../auth/useAuth.ts'
import { getCopy } from '../i18n/copy.ts'

export default function LoginPage() {
  const { status, login } = useAuth()
  const copy = getCopy()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loginError, setLoginError] = useState<string | null>(null)
  const [loggingIn, setLoggingIn] = useState(false)

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

  return (
    <div className="auth-page">
      <span className="auth-brand">Penderie</span>
      <p className="auth-tagline">Bon retour parmi nous.</p>

      <div className="auth-card">
        <h1>Se connecter</h1>
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

        <p className="auth-sub">
          {copy.childSignInNotice}
        </p>

        <p className="auth-switch">
          Pas encore de compte ? <Link to="/inscription">Créer un compte</Link>
        </p>
      </div>
    </div>
  )
}
