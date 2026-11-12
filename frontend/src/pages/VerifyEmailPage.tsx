import { Fragment, useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { ApiError } from '../api/client.ts'
import { useAuth } from '../auth/useAuth.ts'
import { t, tArray } from '../i18n/copy.ts'
import cuteIcon from '../images/icons/cute_icon_email_verif.svg'
import mailIcon from '../images/icons/mail_icon.svg'

export default function VerifyEmailPage() {
  const { verifyEmail, resendVerification } = useAuth()
  const [searchParams] = useSearchParams()
  const location = useLocation()
  const navigate = useNavigate()
  const token = searchParams.get('token')
  const stateEmail = (location.state as { email?: string } | null)?.email ?? null
  const [email, setEmail] = useState(stateEmail ?? '')
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [resending, setResending] = useState(false)

  const sent = token === null && stateEmail !== null

  async function onVerify() {
    if (token === null) return

    setSubmitting(true)
    setError(null)

    try {
      await verifyEmail(token)
      navigate('/foyer/creer', { replace: true })
    } catch (cause) {
      setError(
        cause instanceof ApiError ? t('verify.expiredError') : t('verify.genericError'),
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
      setNotice(t('verify.resendSuccess'))
    } catch {
      setNotice(t('verify.resendFailure'))
    } finally {
      setResending(false)
    }
  }

  return (
    <div className="verify">
      <h1 className="verify__title">
        {tArray<string>('verify.titleLines').map((line, index) => (
          <Fragment key={line}>
            {index > 0 && ' '}
            <span>{line}</span>
          </Fragment>
        ))}
      </h1>

      {error !== null && (
        <p role="alert" className="form-error">
          {error}
        </p>
      )}

      <div className="verify__card">
        {sent && (
          <>
            <p>{t('verify.sentLead')}</p>
            <p className="verify__email">{stateEmail}</p>
            <p>{t('verify.sentInstruction')}</p>
          </>
        )}
        {!sent && token !== null && error === null && <p>{t('verify.tokenNotice')}</p>}
        {!sent && token === null && <p>{t('verify.noTokenLead')}</p>}
      </div>

      <img src={cuteIcon} alt="" className="verify__mascot" />

      {token !== null && error === null ? (
        <div className="verify__form">
          <button
            type="button"
            className="btn btn-primary verify__action"
            onClick={onVerify}
            disabled={submitting}
          >
            {submitting ? t('verify.confirmingButton') : t('verify.confirmButton')}
          </button>
        </div>
      ) : (
        <form className="verify__form" onSubmit={onResend}>
          {stateEmail === null && (
            <div className="pill-group">
              <div className="pill-field">
                <img src={mailIcon} alt="" className="pill-field__icon" />
                <label className="sr-only" htmlFor="verification-email">
                  {t('verify.emailLabel')}
                </label>
                <input
                  id="verification-email"
                  className="pill-field__input"
                  type="email"
                  autoComplete="email"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  required
                />
              </div>
            </div>
          )}
          <button type="submit" className="btn btn-primary verify__action" disabled={resending}>
            {resending ? t('verify.resendingButton') : t('verify.resendButton')}
          </button>
        </form>
      )}

      {notice !== null && (
        <p role="status" className="verify__notice">
          {notice}
        </p>
      )}

      {token === null ? (
        <p className="verify__switch">
          {t('verify.switchPrompt')} <Link to="/connexion">{t('verify.switchLink')}</Link>
        </p>
      ) : (
        <p className="verify__switch">
          <Link to="/connexion">{t('verify.backToLogin')}</Link>
        </p>
      )}
    </div>
  )
}
