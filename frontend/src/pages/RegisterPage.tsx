import { Fragment, useState } from 'react'
import type { FormEvent } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { ApiError } from '../api/client.ts'
import { useAuth } from '../auth/useAuth.ts'
import { getCopy } from '../i18n/copy.ts'
import calendarIcon from '../images/icons/calendar_icon.svg'
import mailIcon from '../images/icons/mail_icon.svg'
import passwordIcon from '../images/icons/password_icon.svg'
import profilIcon from '../images/icons/profil_icon.svg'

function fieldMessage(violations: Record<string, string>, keys: string[]): string | null {
  for (const key of keys) {
    if (violations[key] !== undefined) return violations[key]
  }
  return null
}

export default function RegisterPage() {
  const { status, register } = useAuth()
  const copy = getCopy().register
  const navigate = useNavigate()
  const [username, setUsername] = useState('')
  const [email, setEmail] = useState('')
  const [birthDate, setBirthDate] = useState('')
  const [birthDateFocused, setBirthDateFocused] = useState(false)
  const [password, setPassword] = useState('')
  const [violations, setViolations] = useState<Record<string, string>>({})
  const [globalError, setGlobalError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  if (status === 'authenticated') {
    return <Navigate to="/dressing" replace />
  }

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setViolations({})
    setGlobalError(null)
    setSubmitting(true)

    try {
      await register({
        username: username.trim().toLowerCase(),
        email: email.trim(),
        birthDate,
        password,
        passwordConfirm: password,
      })
      navigate('/verifier-email', { state: { email: email.trim() } })
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
    <div className="register">
      <h1 className="register__title">
        {copy.titleLines.map((line, index) => (
          <Fragment key={line}>
            {index > 0 && ' '}
            <span>{line}</span>
          </Fragment>
        ))}
      </h1>
      <p className="register__intro">{copy.intro}</p>

      <form className="register__form" onSubmit={onSubmit} noValidate>
        {globalError !== null && (
          <p role="alert" className="form-error">
            {globalError}
          </p>
        )}

        <div className="pill-group">
          <div className="pill-field">
            <img src={profilIcon} alt="" className="pill-field__icon" />
            <label className="sr-only" htmlFor="register-username">
              {copy.usernameLabel}
            </label>
            <input
              id="register-username"
              className="pill-field__input"
              type="text"
              autoComplete="username"
              placeholder={copy.usernamePlaceholder}
              value={username}
              onChange={(event) => setUsername(event.target.value)}
              minLength={3}
              maxLength={30}
              required
            />
          </div>
          {fieldMessage(violations, ['username']) !== null && (
            <span className="field-error">{fieldMessage(violations, ['username'])}</span>
          )}
        </div>

        <div className="pill-group">
          <div className="pill-field">
            <img src={mailIcon} alt="" className="pill-field__icon" />
            <label className="sr-only" htmlFor="register-email">
              {copy.emailLabel}
            </label>
            <input
              id="register-email"
              className="pill-field__input"
              type="email"
              autoComplete="email"
              placeholder={copy.emailPlaceholder}
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              required
            />
          </div>
          {fieldMessage(violations, ['email']) !== null && (
            <span className="field-error">{fieldMessage(violations, ['email'])}</span>
          )}
        </div>

        <div className="pill-group">
          <div className="pill-field">
            <img src={calendarIcon} alt="" className="pill-field__icon" />
            <label className="sr-only" htmlFor="register-birth-date">
              {copy.birthDateLabel}
            </label>
            <input
              id="register-birth-date"
              className="pill-field__input"
              type="date"
              autoComplete="bday"
              placeholder={copy.birthDatePlaceholder}
              value={birthDate}
              onFocus={() => setBirthDateFocused(true)}
              onBlur={() => setBirthDateFocused(false)}
              onChange={(event) => setBirthDate(event.target.value)}
              required
            />
            {birthDate === '' && !birthDateFocused && (
              <span className="pill-field__placeholder" aria-hidden="true">
                {copy.birthDatePlaceholder}
              </span>
            )}
          </div>
          <span className="sr-only">{copy.birthDateHint}</span>
          {fieldMessage(violations, ['birthDate']) !== null && (
            <span className="field-error">{fieldMessage(violations, ['birthDate'])}</span>
          )}
        </div>

        <div className="pill-group">
          <div className="pill-field">
            <img src={passwordIcon} alt="" className="pill-field__icon" />
            <label className="sr-only" htmlFor="register-password">
              {copy.passwordLabel}
            </label>
            <input
              id="register-password"
              className="pill-field__input"
              type="password"
              autoComplete="new-password"
              placeholder={copy.passwordPlaceholder}
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              required
              minLength={8}
            />
          </div>
          {fieldMessage(violations, ['password']) !== null && (
            <span className="field-error">{fieldMessage(violations, ['password'])}</span>
          )}
        </div>

        <p className="register__terms">
          {copy.termsLead} <span className="register__terms-link">{copy.termsLink}</span>
        </p>

        <button
          type="submit"
          className="btn btn-primary register__submit"
          disabled={submitting || status === 'loading'}
        >
          {submitting ? 'Inscription en cours…' : copy.submit}
        </button>
      </form>

      <p className="register__switch">
        {copy.switchPrompt} <Link to="/connexion">{copy.switchLink}</Link>
      </p>
    </div>
  )
}
