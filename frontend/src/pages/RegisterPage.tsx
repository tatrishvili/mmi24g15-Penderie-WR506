import { useState } from 'react'
import type { FormEvent } from 'react'
import { Link, Navigate } from 'react-router-dom'
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
  const { status, register, resendVerification } = useAuth()
  const copy = getCopy().register
  const [username, setUsername] = useState('')
  const [email, setEmail] = useState('')
  const [birthDate, setBirthDate] = useState('')
  const [birthDateFocused, setBirthDateFocused] = useState(false)
  const [password, setPassword] = useState('')
  const [submittedEmail, setSubmittedEmail] = useState<string | null>(null)
  const [resendNotice, setResendNotice] = useState<string | null>(null)
  const [resending, setResending] = useState(false)
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
      setSubmittedEmail(email.trim())
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

  async function onResend() {
    if (submittedEmail === null) return

    setResending(true)
    setResendNotice(null)

    try {
      await resendVerification(submittedEmail)
      setResendNotice(
        'Si la demande est admissible, un nouveau lien sera envoyé. Attendez au moins une minute entre deux demandes.',
      )
    } catch {
      setResendNotice('Impossible de traiter la demande pour le moment. Réessayez plus tard.')
    } finally {
      setResending(false)
    }
  }

  return (
    <div className="register">
      {submittedEmail !== null ? (
        <div className="register__panel">
          <h1>Vérifiez votre e-mail</h1>
          <p className="auth-sub">
            Consultez <strong>{submittedEmail}</strong> pour confirmer votre adresse et créer votre
            compte. Si l'adresse peut être utilisée, un lien de confirmation a été envoyé.
          </p>
          {resendNotice !== null && <p role="status">{resendNotice}</p>}
          <button type="button" className="btn btn-secondary" onClick={onResend} disabled={resending}>
            {resending ? 'Envoi en cours…' : 'Renvoyer le lien'}
          </button>
        </div>
      ) : (
        <>
          <h1 className="register__title">
            {copy.titleLines.map((line) => (
              <span key={line}>{line}</span>
            ))}
          </h1>
          <p className="register__intro">{copy.intro}</p>

          <form className="register__form" onSubmit={onSubmit} noValidate>
            {globalError !== null && (
              <p role="alert" className="form-error">
                {globalError}
              </p>
            )}

            <div className="register__group">
              <div className="register__field">
                <img src={profilIcon} alt="" className="register__icon" />
                <label className="sr-only" htmlFor="register-username">
                  {copy.usernameLabel}
                </label>
                <input
                  id="register-username"
                  className="register__input"
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

            <div className="register__group">
              <div className="register__field">
                <img src={mailIcon} alt="" className="register__icon" />
                <label className="sr-only" htmlFor="register-email">
                  {copy.emailLabel}
                </label>
                <input
                  id="register-email"
                  className="register__input"
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

            <div className="register__group">
              <div className="register__field">
                <img src={calendarIcon} alt="" className="register__icon" />
                <label className="sr-only" htmlFor="register-birth-date">
                  {copy.birthDateLabel}
                </label>
                <input
                  id="register-birth-date"
                  className="register__input"
                  type="date"
                  autoComplete="bday"
                  placeholder={copy.birthDatePlaceholder}
                  value={birthDate}
                  onChange={(event) => setBirthDate(event.target.value)}
                  onFocus={() => setBirthDateFocused(true)}
                  onBlur={() => setBirthDateFocused(false)}
                  required
                />
                {birthDate === '' && !birthDateFocused && (
                  <span className="register__placeholder" aria-hidden="true">
                    {copy.birthDatePlaceholder}
                  </span>
                )}
              </div>
              <span className="sr-only">{copy.birthDateHint}</span>
              {fieldMessage(violations, ['birthDate']) !== null && (
                <span className="field-error">{fieldMessage(violations, ['birthDate'])}</span>
              )}
            </div>

            <div className="register__group">
              <div className="register__field">
                <img src={passwordIcon} alt="" className="register__icon" />
                <label className="sr-only" htmlFor="register-password">
                  {copy.passwordLabel}
                </label>
                <input
                  id="register-password"
                  className="register__input"
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
        </>
      )}

      <p className="register__switch">
        {copy.switchPrompt} <Link to="/connexion">{copy.switchLink}</Link>
      </p>
    </div>
  )
}
