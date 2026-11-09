import { errorText } from '../lib/errors.ts'
import { useState } from 'react'
import type { FormEvent } from 'react'
import { Link, Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../auth/useAuth.ts'
import { t } from 'i18next'
import cuteIcon from '../images/icons/cute_icon.svg'
import passwordIcon from '../images/icons/password_icon.svg'
import profilIcon from '../images/icons/profil_icon.svg'

export default function LoginPage() {
  const { status, login, activeProfile } = useAuth()
  const location = useLocation()
  const from = (location.state as { from?: unknown } | null)?.from
  const destination =
    typeof from === 'string' && from.startsWith('/') && !from.startsWith('//') ? from : '/profils'
  const [child, setChild] = useState(false)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [rememberMe, setRememberMe] = useState(false)
  const [loginError, setLoginError] = useState<string | null>(null)
  const [loggingIn, setLoggingIn] = useState(false)

  if (status === 'authenticated') {
    return <Navigate to={activeProfile?.role === 'child' ? '/enfant' : destination} replace />
  }

  async function onPasswordSubmit(event: FormEvent) {
    event.preventDefault()
    setLoginError(null)
    setLoggingIn(true)

    try {
      await login(email.trim(), password, child)
    } catch (error) {
      setLoginError(
        errorText(error),
      )
    } finally {
      setLoggingIn(false)
    }
  }

  return (
    <div className="login">
      <h1 className="login__title">{t('login.title')}</h1>
      <p className="login__subtitle">{t('login.subtitle')}</p>
      <img src={cuteIcon} alt="" className="login__mascot" />

      <div className="family-actions">
        <button
          className="design-link"
          type="button"
          aria-pressed={!child}
          onClick={() => {
            setChild(false)
            setLoginError(null)
          }}
        >
          {t('Connexion adulte', 'Adult sign-in')}
        </button>
        <button
          className="design-link"
          type="button"
          aria-pressed={child}
          onClick={() => {
            setChild(true)
            setLoginError(null)
          }}
        >
          {t('Connexion enfant', 'Child sign-in')}
        </button>
      </div>
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
              {child ? t('childUsernameLabel') : t('login.emailLabel')}
            </label>
            <input
              id="login-email"
              className="pill-field__input"
              type={child ? 'text' : 'email'}
              autoComplete={child ? 'username' : 'email'}
              placeholder={
                child
                  ? t('usernameGivenByYourParent')
                  : t('login.emailPlaceholder')
              }
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
              {t('login.passwordLabel')}
            </label>
            <input
              id="login-password"
              className="pill-field__input"
              type="password"
              autoComplete="current-password"
              placeholder={t('login.passwordPlaceholder')}
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
            {t('login.rememberMe')}
          </label>
          <span className="login__forgot">{t('login.forgotPassword')}</span>
        </div>

        <button
          type="submit"
          className="btn btn-primary login__submit"
          disabled={loggingIn || status === 'loading'}
        >
          {loggingIn ? 'Connexion…' : t('login.submit')}
        </button>
      </form>

      <p className="sr-only">{t('login.childSignInNotice')}</p>

      <p className="login__switch">
        {t('login.switchPrompt')} <Link to="/inscription">{t('login.switchLink')}</Link>
      </p>
    </div>
  )
}