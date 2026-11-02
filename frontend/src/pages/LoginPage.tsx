import { useState } from 'react'
import type { FormEvent } from 'react'
import { Link, Navigate, useLocation } from 'react-router-dom'
import { ApiError } from '../api/client.ts'
import { useAuth } from '../auth/useAuth.ts'
import { getCopy } from '../i18n/copy.ts'
import cuteIcon from '../images/icons/cute_icon.svg'
import passwordIcon from '../images/icons/password_icon.svg'
import profilIcon from '../images/icons/profil_icon.svg'

export default function LoginPage() {
  const { status, login } = useAuth()
  const location = useLocation()
  const from = (location.state as { from?: unknown } | null)?.from
  const destination =
    typeof from === 'string' && from.startsWith('/') && !from.startsWith('//') ? from : '/profils'
  const copy = getCopy()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [rememberMe, setRememberMe] = useState(false)
  const [loginError, setLoginError] = useState<string | null>(null)
  const [loggingIn, setLoggingIn] = useState(false)

  if (status === 'authenticated') {
    return <Navigate to={destination} replace />
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
    <div className="login">
      <h1 className="login__title">{copy.login.title}</h1>
      <p className="login__subtitle">{copy.login.subtitle}</p>
      <img src={cuteIcon} alt="" className="login__mascot" />

      <form className="login__form" onSubmit={onPasswordSubmit} noValidate>
        {loginError !== null && (
          <p role="alert" className="form-error">
            {loginError}
          </p>
        )}

        <div className="pill-group">
          <div className="pill-field">
            <img src={profilIcon} alt="" className="pill-field__icon" />
            <label className="sr-only" htmlFor="login-email">
              {copy.login.emailLabel}
            </label>
            <input
              id="login-email"
              className="pill-field__input"
              type="email"
              autoComplete="email"
              placeholder={copy.login.emailPlaceholder}
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              required
            />
          </div>
        </div>

        <div className="pill-group">
          <div className="pill-field">
            <img src={passwordIcon} alt="" className="pill-field__icon" />
            <label className="sr-only" htmlFor="login-password">
              {copy.login.passwordLabel}
            </label>
            <input
              id="login-password"
              className="pill-field__input"
              type="password"
              autoComplete="current-password"
              placeholder={copy.login.passwordPlaceholder}
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              required
            />
          </div>
        </div>

        <div className="login__options">
          <label className="login__remember">
            <input
              type="checkbox"
              checked={rememberMe}
              onChange={(event) => setRememberMe(event.target.checked)}
            />
            {copy.login.rememberMe}
          </label>
          <span className="login__forgot">{copy.login.forgotPassword}</span>
        </div>

        <button
          type="submit"
          className="btn btn-primary login__submit"
          disabled={loggingIn || status === 'loading'}
        >
          {loggingIn ? 'Connexion…' : copy.login.submit}
        </button>
      </form>

      <p className="sr-only">{copy.childSignInNotice}</p>

      <p className="login__switch">
        {copy.login.switchPrompt} <Link to="/inscription">{copy.login.switchLink}</Link>
      </p>
    </div>
  )
}
