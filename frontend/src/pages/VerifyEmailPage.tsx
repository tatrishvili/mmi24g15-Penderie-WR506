import { useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { ApiError } from '../api/client.ts'
import { useAuth } from '../auth/useAuth.ts'

export default function VerifyEmailPage() {
  const { verifyEmail, resendVerification } = useAuth()
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const token = searchParams.get('token')
  const [email, setEmail] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [resending, setResending] = useState(false)

  async function onVerify() {
    if (token === null) return

    setSubmitting(true)
    setError(null)

    try {
      await verifyEmail(token)
      navigate('/dressing', { replace: true })
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? 'Ce lien est invalide ou a expiré. Vous pouvez demander un nouveau lien ci-dessous.'
          : 'Impossible de confirmer votre adresse pour le moment. Réessayez.',
      )
    } finally {
      setSubmitting(false)
    }
  }

  async function onResend(event: FormEvent) {
    event.preventDefault()
    setResending(true)
    setNotice(null)

    try {
      await resendVerification(email.trim())
      setNotice(
        'Si une inscription en attente existe pour cette adresse, un nouveau lien sera envoyé. Attendez au moins une minute entre deux demandes.',
      )
    } catch {
      setNotice('Impossible de traiter la demande pour le moment. Réessayez plus tard.')
    } finally {
      setResending(false)
    }
  }

  return (
    <div className="auth-page">
      <span className="auth-brand">Penderie</span>
      <div className="auth-card">
        <h1>Confirmer votre adresse e-mail</h1>
        {error !== null && (
          <p role="alert" className="form-error">
            {error}
          </p>
        )}
        {token !== null && (
          <>
            <p className="auth-sub">
              Confirmez cette adresse pour créer votre compte. Votre mot de passe n'est jamais envoyé par e-mail.
            </p>
            <button
              type="button"
              className="btn btn-primary"
              onClick={onVerify}
              disabled={submitting}
            >
              {submitting ? 'Confirmation en cours…' : 'Confirmer et créer mon compte'}
            </button>
          </>
        )}
        {(token === null || error !== null) && (
          <form className="auth-form" onSubmit={onResend}>
            <p className="auth-sub">
              {token === null
                ? 'Le lien ne contient pas de jeton de confirmation. Saisissez votre adresse pour demander un nouveau lien.'
                : 'Vous pouvez demander un nouveau lien de confirmation.'}
            </p>
            <div className="field">
              <label htmlFor="verification-email">E-mail</label>
              <input
                id="verification-email"
                className="input"
                type="email"
                autoComplete="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                required
              />
            </div>
            <button type="submit" className="btn btn-primary" disabled={resending}>
              {resending ? 'Envoi en cours…' : 'Renvoyer le lien'}
            </button>
            {notice !== null && <p role="status">{notice}</p>}
          </form>
        )}
        <p className="auth-switch">
          <Link to="/connexion">Retour à la connexion</Link>
        </p>
      </div>
    </div>
  )
}
