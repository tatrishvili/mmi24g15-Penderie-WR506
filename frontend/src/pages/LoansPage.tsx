import { errorText } from '../lib/errors.ts'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import * as social from '../api/social.ts'
import i18n from '../i18n/config.ts'
import { itemText as tr } from '../lib/itemCatalog.ts'
import { useAuth } from '../auth/useAuth.ts'
import {
  DesignPage,
  DesignRow,
  DesignField,
  DesignForm,
  DesignNotice,
  ErrorMessage,
  FrameIcon,
} from '../components/DesignPage.tsx'

const dateLabel = (value: string | null) =>
  value
    ? new Date(value).toLocaleDateString(i18n.language, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      })
    : tr('À confirmer', 'To be confirmed')

export default function LoansPage({ view }: { view: 'list' | 'request' | 'received' | 'detail' }) {
  const { id, token } = useParams()
  const navigate = useNavigate()
  const [search] = useSearchParams()
  const [loans, setLoans] = useState<social.Loan[]>([])
  const [shared, setShared] = useState<social.SharedItem | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [today] = useState(() => new Date().toLocaleDateString('en-CA'))
  const [due, setDue] = useState('')
  const [message, setMessage] = useState('')
  useEffect(() => {
    let live = true
    if (view === 'request' && token)
      social
        .getShared(token)
        .then((d) => {
          if (live) setShared(d.item)
        })
        .catch((e) => {
          if (live) setError(errorText(e))
        })
        .finally(() => {
          if (live) setLoading(false)
        })
    else
      social
        .listLoans()
        .then((d) => {
          if (live) setLoans(d.loans)
        })
        .catch((e) => {
          if (live) setError(errorText(e))
        })
        .finally(() => {
          if (live) setLoading(false)
        })
    return () => {
      live = false
    }
  }, [view, token])
  const loan = loans.find((l) => l.id === Number(id))
  const frame = { list: '165:454', request: '165:398', received: '165:434', detail: '165:494' }[
    view
  ]
  const icon = (name: string) => <FrameIcon frame={frame} name={name} />
  const run = async (action: () => Promise<void>) => {
    if (busy) return
    setBusy(true)
    setError(null)
    try {
      await action()
    } catch (e) {
      setError(errorText(e))
    } finally {
      setBusy(false)
    }
  }
  const transition = (action: string) =>
    void run(async () => {
      if (loan) {
        const d = await social.transitionLoan(loan.id, action)
        setLoans((v) => v.map((l) => (l.id === loan.id ? d.loan : l)))
        if (view === 'received') navigate(`/prets/${loan.id}`)
      }
    })
  const feedback = (
    <>
      <ErrorMessage error={error} />
      {loading && <p role="status">{tr('Chargement…', 'Loading…')}</p>}
    </>
  )

  if (view === 'request')
    return (
      <DesignPage
        frame={frame}
        className="social-loan-request"
        title={tr('Demander un emprunt', 'Request a loan')}
        subtitle={tr('Une demande nécessite un accord', 'A request needs approval')}
        footer={
          <button
            className="btn btn-primary"
            type="submit"
            form="loan-request"
            disabled={busy || !shared?.available}
          >
            {busy ? tr('Envoi…', 'Sending…') : tr('Envoyer la demande', 'Send request')}
          </button>
        }
      >
        {feedback}
        {shared && (
          <>
            <DesignRow
              icon={icon('imgIconCamera')}
              title={shared.name}
              detail={`${tr('Propriétaire', 'Owner')} · @${shared.owner}`}
              to={`/partage/${token}`}
            />
            <DesignForm
              id="loan-request"
              onSubmit={(e) => {
                e.preventDefault()
                void run(async () => {
                  if (!token) return
                  const d = await social.requestLoan(token, due, message)
                  navigate(`/prets/${d.loan.id}`)
                })
              }}
            >
              <DesignField label={tr('Date de retour souhaitée', 'Requested return date')}>
                <span className="loan-date">
                  {icon('imgIconCalendar')}
                  <input
                    required
                    aria-label={tr('Choisir une date', 'Choose a date')}
                    type="date"
                    min={today}
                    value={due}
                    onChange={(e) => setDue(e.target.value)}
                  />
                </span>
              </DesignField>
              <DesignField label={tr('Message facultatif', 'Optional message')}>
                <textarea
                  rows={4}
                  maxLength={2000}
                  placeholder={tr('Bonjour, puis-je emprunter cet appareil pour le week-end ?', 'Hello, may I borrow this device for the weekend?')}
                  value={message}
                  onChange={(e) => setMessage(e.target.value)}
                />
              </DesignField>
              <DesignRow
                icon={icon('imgIconShield')}
                title={tr('Demande privée', 'Private request')}
                detail={tr('Le propriétaire décide', 'The owner decides')}
              />
              {!shared.available && <p role="status">{tr('Cet article n’est plus disponible.', 'This item is no longer available.')}</p>}
            </DesignForm>
          </>
        )}
      </DesignPage>
    )

  if (view === 'list') {
    const visible = search.get('demandes') ? loans.filter((l) => l.status === 'pending') : loans
    return (
      <DesignPage
        frame={frame}
        className="social-loans"
        title={tr('Mes prêts', 'My loans')}
        subtitle={tr('Suivre les objets prêtés ou empruntés', 'Track lent and borrowed items')}
        footer={
          <Link
            className="btn btn-primary"
            to={search.get('demandes') ? '/prets' : '/prets?demandes=1'}
          >
            {search.get('demandes') ? tr('Voir tous les prêts', 'See all loans') : tr('Voir toutes les demandes', 'See all requests')}
          </Link>
        }
      >
        {feedback}
        {!loading && !visible.length && (
          <p>
            {search.get('demandes')
              ? tr('Aucun prêt en attente. Les demandes reçues et envoyées apparaîtront ici.', 'No pending loans. Received and sent requests will appear here.')
              : tr('Aucun prêt pour le moment. Les demandes reçues et envoyées apparaîtront ici.', 'No loans yet. Received and sent requests will appear here.')}
          </p>
        )}
        {visible.map((l) => (
          <DesignRow
            key={l.id}
            icon={icon(
              l.itemName.toLowerCase().includes('vinyl')
                ? 'imgIconDisc'
                : l.itemName.toLowerCase().includes('pull')
                  ? 'imgIconShirt'
                  : 'imgIconCamera',
            )}
            title={l.itemName}
            detail={`${l.isOwner ? l.borrower : l.owner} · ${social.loanLabel(l.status)}`}
            to={l.isOwner && l.status === 'pending' ? `/prets/${l.id}/demande` : `/prets/${l.id}`}
          />
        ))}
        {loans.some((l) => l.isOwner && l.status === 'active') && (
          <DesignRow
            icon={icon('imgIconClock')}
            title={tr('Retour à confirmer', 'Return to confirm')}
            detail={tr('Vérifier l’article et confirmer', 'Check the item and confirm')}
            to={`/prets/${loans.find((l) => l.isOwner && l.status === 'active')?.id}`}
          />
        )}
      </DesignPage>
    )
  }

  if (view === 'received')
    return (
      <DesignPage
        frame={frame}
        className="social-loan-received"
        title={tr('Demande reçue', 'Request received')}
        subtitle={tr('Accepter ou refuser un emprunt', 'Accept or decline a loan')}
      >
        {feedback}
        {!loading && !loan && <p>{tr('Demande introuvable.', 'Request not found.')}</p>}
        {loan && (
          <>
            <DesignRow
              icon={icon('imgIconUser')}
              title={loan.borrower}
              detail={`${tr('Souhaite emprunter votre', 'Wants to borrow your')} ${loan.itemName}`}
            />
            <DesignNotice>
              <h2>{tr('Retour souhaité', 'Requested return')} : {dateLabel(loan.dueAt)}</h2>
              <p>{loan.message || tr('Aucun message.', 'No message.')}</p>
            </DesignNotice>
            {loan.isOwner && loan.status === 'pending' ? (
              <div className="social-loan-received__actions">
                <button
                  className="btn btn-primary"
                  disabled={busy}
                  onClick={() => transition('accept')}
                >
                  {tr('Accepter', 'Accept')}
                </button>
                <button
                  className="btn btn-secondary"
                  disabled={busy}
                  onClick={() => transition('decline')}
                >
                  {tr('Refuser', 'Decline')}
                </button>
              </div>
            ) : (
              <p>{social.loanLabel(loan.status)}</p>
            )}
            <small>{tr('L’accord ne confirme pas encore la remise physique.', 'Approval does not confirm the physical handover yet.')}</small>
          </>
        )}
      </DesignPage>
    )

  return (
    <DesignPage
      frame={frame}
      className="social-loan-detail"
      title={tr('Détail du prêt', 'Loan details')}
      subtitle={tr('Retour et historique de l’objet', 'Return and item history')}
      footer={
        loan && (
          <>
            {loan.isOwner && loan.status === 'accepted' && (
              <button
                className="btn btn-primary"
                disabled={busy}
                onClick={() => transition('handover')}
              >
                {tr('Confirmer la remise', 'Confirm handover')}
              </button>
            )}
            {loan.isOwner && ['active', 'accepted'].includes(loan.status) && (
              <button
                className="btn btn-primary"
                disabled={busy}
                onClick={() => transition('return')}
              >
                {tr('Confirmer le retour', 'Confirm return')}
              </button>
            )}
            <Link className="design-link" to="/prets">
              {tr('Mes prêts', 'My loans')}
            </Link>
          </>
        )
      }
    >
      {feedback}
      {!loading && !loan && <p>{tr('Prêt introuvable.', 'Loan not found.')}</p>}
      {loan && (
        <>
          <DesignRow
            icon={icon('imgIconCamera')}
            title={loan.itemName}
            detail={`${tr('Avec', 'With')} ${loan.isOwner ? loan.borrower : loan.owner} · ${social.loanLabel(loan.status)}`}
            to={loan.itemId ? `/articles/${loan.itemId}` : undefined}
          />
          <DesignRow
            icon={icon('imgIconClock')}
            title={tr('Demande acceptée', 'Request accepted')}
            detail={dateLabel(loan.acceptedAt)}
          />
          <DesignRow
            icon={icon('imgIconClock')}
            title={tr('Objet remis', 'Item handed over')}
            detail={dateLabel(loan.handedAt)}
          />
          <DesignRow
            icon={icon('imgIconClock')}
            title={tr('Retour prévu', 'Expected return')}
            detail={dateLabel(loan.dueAt)}
          />
          <DesignRow
            icon={icon('imgIconShield')}
            title={loan.returnedAt ? tr('Réception confirmée', 'Receipt confirmed') : tr('Réception à confirmer', 'Receipt to confirm')}
            detail={loan.returnedAt ? dateLabel(loan.returnedAt) : tr('Après restitution', 'After return')}
          />
          {loan.isOwner && loan.status === 'pending' && (
            <Link className="design-link" to={`/prets/${loan.id}/demande`}>
              {tr('Répondre à la demande', 'Answer the request')}
            </Link>
          )}
        </>
      )}
    </DesignPage>
  )
}

export function SharedItemPage() {
  const { token } = useParams()
  const auth = useAuth()
  const [item, setItem] = useState<social.SharedItem | null>(null)
  const [error, setError] = useState<string | null>(null)
  useEffect(() => {
    const meta = document.createElement('meta')
    meta.name = 'referrer'
    meta.content = 'no-referrer'
    document.head.append(meta)
    let live = true
    if (token)
      social
        .getShared(token)
        .then((d) => {
          if (live) setItem(d.item)
        })
        .catch((e) => {
          if (live) setError(errorText(e))
        })
    return () => {
      live = false
      meta.remove()
    }
  }, [token, auth.status])
  return (
    <DesignPage
      frame="165:308"
      className="social-item"
      title={item?.name ?? tr('Article partagé', 'Shared item')}
      subtitle={tr('Présentation limitée · Lecture seule', 'Limited view · Read only')}
      footer={
        auth.status === 'authenticated' && item?.available ? (
          <Link className="btn btn-primary" to={`/emprunter/${token}`}>
            {tr('Demander un emprunt', 'Request a loan')}
          </Link>
        ) : (
          <Link className="btn btn-secondary" to="/connexion" state={{ from: `/partage/${token}` }}>
            {tr('Se connecter', 'Sign in')}
          </Link>
        )
      }
    >
      <ErrorMessage error={error} />
      {item && (
        <>
          <div className="social-item__photo">
            <>
              {item.photoUrl ? (
                <img src={item.photoUrl} alt={item.name} referrerPolicy="no-referrer" />
              ) : (
                <FrameIcon frame="165:308" name="imgIconShirt" />
              )}
            </>
          </div>
          <DesignRow
            title={tr('Article', 'Item')}
            detail={[item.type, item.color].filter(Boolean).join(' · ') || item.name}
          />
          <DesignRow title={tr('Partagé par', 'Shared by')} detail={`@${item.owner}`} />
          <DesignNotice>
            {tr('Le lieu de rangement, l’adresse et les notes privées ne sont pas partagés.', 'Storage location, address and private notes are not shared.')}
          </DesignNotice>
        </>
      )}
    </DesignPage>
  )
}
