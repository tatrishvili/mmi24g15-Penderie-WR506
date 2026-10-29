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
  const { status, register, resendVerification } = useAuth()
  const [username, setUsername] = useState('')
  const [email, setEmail] = useState('')
  const [birthDate, setBirthDate] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirm, setPasswordConfirm] = useState('')
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

    if (password !== passwordConfirm) {
      setViolations({ passwordConfirm: 'La confirmation ne correspond pas au mot de passe.' })
      return
    }

    setSubmitting(true)

    try {
      await register({
        username: username.trim().toLowerCase(),
        email: email.trim(),
        birthDate,
        password,
        passwordConfirm,
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
    <div className="auth-page">
      <span className="auth-brand">Penderie</span>
      <p className="auth-tagline">Tout ce que vous possedez, au meme endroit.</p>

      <div className="auth-card">
        {submittedEmail !== null ? (
          <>
            <h1>Vérifiez votre e-mail</h1>
            <p className="auth-sub">
              Consultez <strong>{submittedEmail}</strong> pour confirmer votre adresse et créer votre compte.
              Si l'adresse peut être utilisée, un lien de confirmation a été envoyé.
            </p>
            {resendNotice !== null && <p role="status">{resendNotice}</p>}
            <button type="button" className="btn btn-secondary" onClick={onResend} disabled={resending}>
              {resending ? 'Envoi en cours…' : 'Renvoyer le lien'}
            </button>
          </>
        ) : (
          <>
            <h1>Inscription</h1>
            <p className="auth-sub">
              Choisissez votre nom d’utilisateur, indiquez votre date de naissance et confirmez votre e-mail pour créer votre compte.
            </p>

            <form className="auth-form" onSubmit={onSubmit} noValidate>
              {globalError !== null && (
                <p role="alert" className="form-error">
                  {globalError}
                </p>
              )}

              <div className="field">
                <label htmlFor="register-username">Nom d’utilisateur</label>
                <input
                  id="register-username"
                  className="input"
                  type="text"
                  autoComplete="username"
                  value={username}
                  onChange={(event) => setUsername(event.target.value)}
                  minLength={3}
                  maxLength={30}
                  required
                />
                <span className="auth-sub">Unique, de 3 à 30 lettres, chiffres ou tirets bas (_).</span>
                {fieldMessage(violations, ['username']) !== null && (
                  <span className="field-error">{fieldMessage(violations, ['username'])}</span>
                )}
              </div>

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
                <label htmlFor="register-birth-date">Date de naissance</label>
                <input
                  id="register-birth-date"
                  className="input"
                  type="date"
                  autoComplete="bday"
                  value={birthDate}
                  onChange={(event) => setBirthDate(event.target.value)}
                  required
                />
                <span className="auth-sub">
                  Cette date est déclarative, pas une vérification officielle. L’inscription adulte est réservée aux personnes de 18 ans ou plus; les mineurs doivent utiliser un profil créé par un parent.
                </span>
                {fieldMessage(violations, ['birthDate']) !== null && (
                  <span className="field-error">{fieldMessage(violations, ['birthDate'])}</span>
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

              <button
                type="submit"
                className="btn btn-primary"
                disabled={submitting || status === 'loading'}
              >
                {submitting ? 'Inscription en cours…' : "S'inscrire"}
              </button>
            </form>
          </>
        )}

        <p className="auth-switch">
          Deja un compte ? <Link to="/connexion">Se connecter</Link>
        </p>
      </div>
    </div>
  )
}