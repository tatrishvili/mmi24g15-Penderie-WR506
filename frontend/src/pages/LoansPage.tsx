import { errorText } from '../lib/errors.ts'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import * as social from '../api/social.ts'
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
    ? new Date(value).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      })
    : 'À confirmer'

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
      {loading && <p role="status">Chargement…</p>}
    </>
  )

  if (view === 'request')
    return (
      <DesignPage
        frame={frame}
        className="social-loan-request"
        title="Demander un emprunt"
        subtitle="Une demande nécessite un accord"
        footer={
          <button
            className="btn btn-primary"
            type="submit"
            form="loan-request"
            disabled={busy || !shared?.available}
          >
            {busy ? 'Envoi…' : 'Envoyer la demande'}
          </button>
        }
      >
        {feedback}
        {shared && (
          <>
            <DesignRow
              icon={icon('imgIconCamera')}
              title={shared.name}
              detail={`Propriétaire · @${shared.owner}`}
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
              <DesignField label="Date de retour souhaitée">
                <span className="loan-date">
                  {icon('imgIconCalendar')}
                  <input
                    required
                    aria-label="Choisir une date"
                    type="date"
                    min={today}
                    value={due}
                    onChange={(e) => setDue(e.target.value)}
                  />
                </span>
              </DesignField>
              <DesignField label="Message facultatif">
                <textarea
                  rows={4}
                  maxLength={2000}
                  placeholder="Bonjour, puis-je emprunter cet appareil pour le week-end ?"
                  value={message}
                  onChange={(e) => setMessage(e.target.value)}
                />
              </DesignField>
              <DesignRow
                icon={icon('imgIconShield')}
                title="Demande privée"
                detail="Le propriétaire décide"
              />
              {!shared.available && <p role="status">Cet article n’est plus disponible.</p>}
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
        title="Mes prêts"
        subtitle="Suivre les objets prêtés ou empruntés"
        footer={
          <Link
            className="btn btn-primary"
            to={search.get('demandes') ? '/prets' : '/prets?demandes=1'}
          >
            {search.get('demandes') ? 'Voir tous les prêts' : 'Voir toutes les demandes'}
          </Link>
        }
      >
        {feedback}
        {!loading && !visible.length && (
          <p>
            Aucun prêt {search.get('demandes') ? 'en attente' : 'pour le moment'}. Les demandes
            reçues et envoyées apparaîtront ici.
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
            detail={`${l.isOwner ? l.borrower : l.owner} · ${social.loanLabel[l.status]}`}
            to={l.isOwner && l.status === 'pending' ? `/prets/${l.id}/demande` : `/prets/${l.id}`}
          />
        ))}
        {loans.some((l) => l.isOwner && l.status === 'active') && (
          <DesignRow
            icon={icon('imgIconClock')}
            title="Retour à confirmer"
            detail="Vérifier l’article et confirmer"
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
        title="Demande reçue"
        subtitle="Accepter ou refuser un emprunt"
      >
        {feedback}
        {!loading && !loan && <p>Demande introuvable.</p>}
        {loan && (
          <>
            <DesignRow
              icon={icon('imgIconUser')}
              title={loan.borrower}
              detail={`Souhaite emprunter votre ${loan.itemName}`}
            />
            <DesignNotice>
              <h2>Retour souhaité : {dateLabel(loan.dueAt)}</h2>
              <p>{loan.message || 'Aucun message.'}</p>
            </DesignNotice>
            {loan.isOwner && loan.status === 'pending' ? (
              <div className="social-loan-received__actions">
                <button
                  className="btn btn-primary"
                  disabled={busy}
                  onClick={() => transition('accept')}
                >
                  Accepter
                </button>
                <button
                  className="btn btn-secondary"
                  disabled={busy}
                  onClick={() => transition('decline')}
                >
                  Refuser
                </button>
              </div>
            ) : (
              <p>{social.loanLabel[loan.status]}</p>
            )}
            <small>L’accord ne confirme pas encore la remise physique.</small>
          </>
        )}
      </DesignPage>
    )

  return (
    <DesignPage
      frame={frame}
      className="social-loan-detail"
      title="Détail du prêt"
      subtitle="Retour et historique de l’objet"
      footer={
        loan && (
          <>
            {loan.isOwner && loan.status === 'accepted' && (
              <button
                className="btn btn-primary"
                disabled={busy}
                onClick={() => transition('handover')}
              >
                Confirmer la remise
              </button>
            )}
            {loan.isOwner && ['active', 'accepted'].includes(loan.status) && (
              <button
                className="btn btn-primary"
                disabled={busy}
                onClick={() => transition('return')}
              >
                Confirmer le retour
              </button>
            )}
            <Link className="design-link" to="/prets">
              Mes prêts
            </Link>
          </>
        )
      }
    >
      {feedback}
      {!loading && !loan && <p>Prêt introuvable.</p>}
      {loan && (
        <>
          <DesignRow
            icon={icon('imgIconCamera')}
            title={loan.itemName}
            detail={`Avec ${loan.isOwner ? loan.borrower : loan.owner} · ${social.loanLabel[loan.status]}`}
            to={loan.itemId ? `/articles/${loan.itemId}` : undefined}
          />
          <DesignRow
            icon={icon('imgIconClock')}
            title="Demande acceptée"
            detail={dateLabel(loan.acceptedAt)}
          />
          <DesignRow
            icon={icon('imgIconClock')}
            title="Objet remis"
            detail={dateLabel(loan.handedAt)}
          />
          <DesignRow
            icon={icon('imgIconClock')}
            title="Retour prévu"
            detail={dateLabel(loan.dueAt)}
          />
          <DesignRow
            icon={icon('imgIconShield')}
            title={loan.returnedAt ? 'Réception confirmée' : 'Réception à confirmer'}
            detail={loan.returnedAt ? dateLabel(loan.returnedAt) : 'Après restitution'}
          />
          {loan.isOwner && loan.status === 'pending' && (
            <Link className="design-link" to={`/prets/${loan.id}/demande`}>
              Répondre à la demande
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
      title={item?.name ?? 'Article partagé'}
      subtitle="Présentation limitée · Lecture seule"
      footer={
        auth.status === 'authenticated' && item?.available ? (
          <Link className="btn btn-primary" to={`/emprunter/${token}`}>
            Demander un emprunt
          </Link>
        ) : (
          <Link className="btn btn-secondary" to="/connexion" state={{ from: `/partage/${token}` }}>
            Se connecter
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
            title="Article"
            detail={[item.type, item.color].filter(Boolean).join(' · ') || item.name}
          />
          <DesignRow title="Partagé par" detail={`@${item.owner}`} />
          <DesignNotice>
            Le lieu de rangement, l’adresse et les notes privées ne sont pas partagés.
          </DesignNotice>
        </>
      )}
    </DesignPage>
  )
}
