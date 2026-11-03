import ChildSnapshotPreview from '../components/ChildSnapshotPreview.tsx'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import * as family from '../api/family.ts'
import { useAuth } from '../auth/useAuth.ts'
import { DesignPage, ErrorMessage } from '../components/DesignPage.tsx'
import { errorText } from '../lib/errors.ts'
import { itemText as t } from '../lib/itemCatalog.ts'

const decisionText = (status?: string) =>
  ({
    pending: t('En attente', 'Pending'),
    approved: t('Approuvé', 'Approved'),
    rejected: t('Refusé', 'Declined'),
    withdrawn: t('Retiré', 'Withdrawn'),
    active: t('Actif', 'Active'),
    revoked: t('Retiré', 'Revoked'),
  })[status ?? ''] ?? t('Indisponible', 'Unavailable')
export default function NotificationsPage() {
  const auth = useAuth()
  const [rows, setRows] = useState<family.Notification[]>([])
  const [unread, setUnread] = useState(0)
  const [before, setBefore] = useState<number | null>(null)
  const [busy, setBusy] = useState(false)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState('')
  const identity =
    auth.activeProfile?.role === 'child'
      ? `child:${auth.activeProfileId}`
      : `adult:${auth.user?.id}`
  const load = async () => {
    const data = await family.inbox()
    setRows(data.notifications)
    setUnread(data.unread)
    setBefore(data.nextBefore)
  }
  useEffect(() => {
    let live = true
    family
      .inbox()
      .then((data) => {
        if (live) {
          setRows(data.notifications)
          setUnread(data.unread)
          setBefore(data.nextBefore)
        }
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
  }, [auth.activeProfileId])
  async function run(action: () => Promise<unknown>) {
    if (busy) return
    setBusy(true)
    setError(null)
    setNotice('')
    try {
      await action()
      await load()
      await auth.refresh()
      setNotice(t('Décision enregistrée.', 'Decision saved.'))
    } catch (e) {
      setError(errorText(e))
    } finally {
      setBusy(false)
    }
  }
  return (
    <DesignPage
      frame="165:8"
      title={t('Notifications', 'Notifications')}
      subtitle={`${unread} ${t('non lue(s)', 'unread')}`}
    >
      <Link className="btn btn-secondary" to="/partages-famille">
        {t('Voir les partages familiaux', 'View family shares')}
      </Link>
      <ErrorMessage error={error} />
      {notice && <p role="status">{notice}</p>}
      {loading && <p role="status">{t('Chargement…', 'Loading…')}</p>}
      {!loading && rows.length === 0 && (
        <p>{t('Aucune notification pour le moment.', 'No notifications yet.')}</p>
      )}
      {rows.map((row) => {
        const detail = row.detail
        const entries = detail?.proposal?.preview
          ? [detail.proposal.preview]
          : (detail?.proposal?.snapshot?.items ?? [])
        return (
          <article className="family-section" key={row.id}>
            <h2>
              {row.kind === 'parent_invitation'
                ? t('Invitation comme parent', 'Parent invitation')
                : row.kind === 'connection'
                  ? t('Demande de contact', 'Contact request')
                  : row.kind === 'decision'
                    ? t('Réponse à ta demande', 'Response to your request')
                    : t('Demande de partage', 'Sharing request')}
            </h2>
            {!row.read && <span className="family-badge">{t('Nouveau', 'New')}</span>}
            {!detail ? (
              <p>
                {t('Cette demande n’est plus accessible.', 'This request is no longer accessible.')}
              </p>
            ) : (
              <>
                {detail.childName && <p>{detail.childName}</p>}
                {row.kind === 'parent_invitation' ? (
                  <>
                    <p>
                      {t('Vous êtes invité(e) comme', 'You are invited as')}{' '}
                      {detail.relationship === 'mother' ? t('mère', 'mother') : t('père', 'father')}
                      .{' '}
                      {t(
                        'Accepter vous ajoute au foyer et vous permet de gérer cet enfant.',
                        'Accepting joins the household and lets you manage this child.',
                      )}
                    </p>
                    <p>{decisionText(detail.state)}</p>
                    {detail.state === 'pending' && (
                      <div className="family-actions">
                        <button
                          className="btn btn-primary"
                          disabled={busy}
                          onClick={() => void run(() => family.acceptParent(detail.id))}
                        >
                          {t('Accepter mon rôle de parent', 'Accept my parent role')}
                        </button>
                        <button
                          className="btn btn-secondary"
                          disabled={busy}
                          onClick={() => void run(() => family.declineParent(detail.id))}
                        >
                          {t('Refuser', 'Decline')}
                        </button>
                      </div>
                    )}
                  </>
                ) : row.kind === 'connection' ? (
                  <>
                    <p>
                      {detail.requesterName} → {detail.recipientName}
                    </p>
                    <p>{decisionText(detail.state)}</p>
                    {detail.state === 'pending' && (
                      <div className="family-actions">
                        {detail.recipientKey === identity && !detail.recipientAccepted && (
                          <button
                            className="btn btn-primary"
                            disabled={busy}
                            onClick={() =>
                              void run(() => family.connectionAction(detail.id, 'accept'))
                            }
                          >
                            {t('Accepter ce contact', 'Accept this contact')}
                          </button>
                        )}
                        {auth.activeProfile?.role !== 'child' &&
                          detail.recipientKey !== identity && (
                            <button
                              className="btn btn-primary"
                              disabled={busy}
                              onClick={() =>
                                void run(() => family.connectionAction(detail.id, 'approve'))
                              }
                            >
                              {t('Approuver pour mon enfant', 'Approve for my child')}
                            </button>
                          )}
                        <button
                          className="btn btn-secondary"
                          disabled={busy}
                          onClick={() =>
                            void run(() => family.connectionAction(detail.id, 'reject'))
                          }
                        >
                          {t('Refuser', 'Decline')}
                        </button>
                      </div>
                    )}
                  </>
                ) : (
                  <>
                    <p>{decisionText(detail.status)}</p>
                    {detail.reviewable === false && (
                      <p role="status">
                        {t(
                          'Le contenu a changé ou l’accès a été retiré. Un nouvel accord est nécessaire.',
                          'The content changed or access was removed. New approval is required.',
                        )}
                      </p>
                    )}
                    {detail.proposal?.snapshot?.name && <h3>{detail.proposal.snapshot.name}</h3>}
                    <div className="family-previews">
                      {entries.map((entry, index) => (
                        <ChildSnapshotPreview
                          key={index}
                          item={entry}
                          photoUrls={
                            detail.reviewable === false
                              ? []
                              : entry.photos.map(
                                  (_, photoIndex) =>
                                    `/api/child-approvals/${detail.id}/photos/${index}/${photoIndex}`,
                                )
                          }
                        />
                      ))}
                    </div>
                    <p>
                      {t('Destinataires', 'Recipients')} :{' '}
                      {detail.proposal?.recipients
                        ?.map((recipient) =>
                          typeof recipient === 'string'
                            ? (detail.proposal?.recipientNames?.[recipient] ??
                              t('Contact approuvé', 'Approved contact'))
                            : recipient.name,
                        )
                        .join(', ')}
                    </p>
                    <p className="design-hint">
                      {t(
                        'Seul cet aperçu sera partagé. Les notes et emplacements restent cachés.',
                        'Only this preview will be shared. Notes and locations stay hidden.',
                      )}
                    </p>
                    {detail.canDecide && detail.version && (
                      <div className="family-actions">
                        <button
                          className="btn btn-primary"
                          disabled={busy || detail.reviewable === false}
                          onClick={() =>
                            void run(() => family.decideApproval(detail.id, detail.version!, true))
                          }
                        >
                          {t('Approuver cet aperçu', 'Approve this preview')}
                        </button>
                        <button
                          className="btn btn-secondary"
                          disabled={busy}
                          onClick={() =>
                            void run(() => family.decideApproval(detail.id, detail.version!, false))
                          }
                        >
                          {t('Refuser', 'Decline')}
                        </button>
                      </div>
                    )}
                    {auth.activeProfile?.role === 'child' && detail.status !== 'withdrawn' && (
                      <button
                        className="btn btn-secondary"
                        disabled={busy}
                        onClick={() => void run(() => family.withdrawApproval(detail.id))}
                      >
                        {t('Retirer cette demande ou ce partage', 'Withdraw this request or share')}
                      </button>
                    )}
                  </>
                )}
              </>
            )}
            {!row.read && (
              <button
                className="design-link"
                disabled={busy}
                onClick={() => void run(() => family.readNotification(row.id))}
              >
                {t('Marquer comme lu', 'Mark as read')}
              </button>
            )}
          </article>
        )
      })}
      {before && (
        <button
          className="btn btn-secondary"
          disabled={busy}
          onClick={() => {
            setBusy(true)
            void family
              .inbox(before)
              .then((data) => {
                setRows((values) => [...values, ...data.notifications])
                setBefore(data.nextBefore)
              })
              .catch((e) => setError(errorText(e)))
              .finally(() => setBusy(false))
          }}
        >
          {t('Voir les notifications précédentes', 'Load older notifications')}
        </button>
      )}
      <Link
        className="design-link"
        to={auth.activeProfile?.role === 'child' ? '/enfant' : '/foyer/enfants'}
      >
        {t('Comptes enfants et famille', 'Child accounts and family')}
      </Link>
    </DesignPage>
  )
}
