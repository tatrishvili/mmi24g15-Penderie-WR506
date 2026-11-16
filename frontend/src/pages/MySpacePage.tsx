import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Activity, X } from 'lucide-react'
import {
  dismissMySpaceActivity,
  fetchMySpace,
  markLoanReturned,
  revokeMySpaceShare,
} from '../api/mySpace.ts'
import type { MySpaceData } from '../api/mySpace.ts'
import { DesignPage, ErrorMessage } from '../components/DesignPage.tsx'
import { errorText } from '../lib/errors.ts'

type Detail = 'items' | 'places' | 'household' | 'loans'

export default function MySpacePage() {
  const { t, i18n } = useTranslation()
  const [space, setSpace] = useState<MySpaceData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [detail, setDetail] = useState<Detail | null>(null)
  const [busyLoanId, setBusyLoanId] = useState<number | null>(null)
  const [busyShareId, setBusyShareId] = useState<number | null>(null)
  const [busyActivityId, setBusyActivityId] = useState<number | null>(null)

  async function loadSpace() {
    setError(null)
    const data = await fetchMySpace()
    setSpace(data)
  }

  useEffect(() => {
    let active = true
    fetchMySpace()
      .then((data) => {
        if (active) setSpace(data)
      })
      .catch((cause: unknown) => {
        if (active) setError(errorText(cause))
      })
      .finally(() => {
        if (active) setLoading(false)
      })
    return () => {
      active = false
    }
  }, [])

  const formatDateTime = (value: string) =>
    new Intl.DateTimeFormat(i18n.resolvedLanguage ?? i18n.language, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(value))

  async function returnLoan(id: number) {
    setBusyLoanId(id)
    setError(null)
    try {
      await markLoanReturned(id)
      await loadSpace()
    } catch (cause) {
      setError(errorText(cause))
    } finally {
      setBusyLoanId(null)
    }
  }

  async function revokeShare(id: number) {
    setBusyShareId(id)
    setError(null)
    try {
      await revokeMySpaceShare(id)
      await loadSpace()
    } catch (cause) {
      setError(errorText(cause))
    } finally {
      setBusyShareId(null)
    }
  }

  async function dismissActivity(id: number) {
    setBusyActivityId(id)
    setError(null)
    try {
      await dismissMySpaceActivity(id)
      await loadSpace()
    } catch (cause) {
      setError(errorText(cause))
    } finally {
      setBusyActivityId(null)
    }
  }

  const cards: { id: Detail; label: string; description: string; count: number }[] =
    space === null
      ? []
      : space.isChild
        ? [
            {
              id: 'items',
              label: t('mySpaceDashboard.cards.items'),
              description: t('mySpaceDashboard.cards.itemsDescription'),
              count: space.stats.items,
            },
            {
              id: 'loans',
              label: t('mySpaceDashboard.cards.loans'),
              description: t('mySpaceDashboard.cards.loansDescription'),
              count: space.stats.activeLoans,
            },
          ]
        : [
            {
              id: 'items',
              label: t('mySpaceDashboard.cards.items'),
              description: t('mySpaceDashboard.cards.itemsDescription'),
              count: space.stats.items,
            },
            {
              id: 'places',
              label: t('mySpaceDashboard.cards.places'),
              description: t('mySpaceDashboard.cards.placesDescription'),
              count: space.stats.places,
            },
            {
              id: 'household',
              label: t('mySpaceDashboard.cards.household'),
              description: t('mySpaceDashboard.cards.householdDescription'),
              count: space.stats.profiles,
            },
            {
              id: 'loans',
              label: t('mySpaceDashboard.cards.loans'),
              description: t('mySpaceDashboard.cards.loansDescription'),
              count: space.stats.activeLoans,
            },
          ]

  const incompleteItems =
    space?.items.filter((item) => item.place.trim() === '' || item.photoUrl === null) ?? []

  return (
    <DesignPage
      frame="my-space"
      title={t('mySpaceDashboard.title')}
      subtitle={space?.household.name ?? t('mySpaceDashboard.subtitle')}
      className="my-space-page"
    >
      <ErrorMessage error={error} />
      {loading && <p role="status">{t('mySpaceDashboard.loading')}</p>}
      {!loading && space !== null && (
        <>
          <div className="my-space-stat-grid">
            {cards.map((card) => (
              <button
                type="button"
                className="my-space-stat"
                key={card.id}
                onClick={() => setDetail(card.id)}
                aria-haspopup="dialog"
                aria-expanded={detail === card.id}
              >
                <span className="my-space-stat__label">{card.label}</span>
                <span className="my-space-stat__description">{card.description}</span>
                <strong>{card.count}</strong>
              </button>
            ))}
          </div>

          {!space.isChild && (
            <section className="my-space-section" aria-labelledby="my-space-sale-title">
              <h2 id="my-space-sale-title">{t('mySpaceDashboard.sale.title')}</h2>
              <p className="my-space-empty">{t('mySpaceDashboard.sale.unavailable')}</p>
            </section>
          )}

          {!space.isChild && (
            <section className="my-space-section" aria-labelledby="my-space-shares-title">
              <h2 id="my-space-shares-title">{t('mySpaceDashboard.shares.title')}</h2>
              {space.shares.length === 0 ? (
                <p className="my-space-empty">{t('mySpaceDashboard.shares.empty')}</p>
              ) : (
                <ul className="my-space-list">
                  {space.shares.map((share) => (
                    <li key={share.id} className="my-space-row my-space-share">
                      <span className="my-space-share__content">
                        <small className="my-space-share__label">
                          {t('mySpaceDashboard.shares.active')}
                        </small>
                        <strong>{share.itemName}</strong>
                        <small className="my-space-share__expiry">
                          {t('mySpaceDashboard.shares.expires', {
                            date: formatDateTime(share.expiresAt),
                          })}
                        </small>
                      </span>
                      <button
                        type="button"
                        className="btn btn-secondary my-space-share__revoke"
                        disabled={busyShareId !== null}
                        onClick={() => void revokeShare(share.id)}
                      >
                        {busyShareId === share.id
                          ? t('mySpaceDashboard.shares.revoking')
                          : t('mySpaceDashboard.shares.revoke')}
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          )}

          {!space.isChild && (
            <section className="my-space-section" aria-labelledby="my-space-comments-title">
              <h2 id="my-space-comments-title">{t('mySpaceDashboard.comments.title')}</h2>
              <p className="my-space-empty">{t('mySpaceDashboard.comments.unavailable')}</p>
            </section>
          )}

          <section className="my-space-section" aria-labelledby="my-space-incomplete-title">
            <h2 id="my-space-incomplete-title">{t('mySpaceDashboard.incomplete.title')}</h2>
            {incompleteItems.length === 0 ? (
              <p className="my-space-empty">{t('mySpaceDashboard.incomplete.empty')}</p>
            ) : (
              <ul className="my-space-list">
                {incompleteItems.map((item) => (
                  <li className="my-space-row" key={item.id}>
                    <span>
                      <strong>{item.name}</strong>
                      <small>
                        {[
                          item.place.trim() === ''
                            ? t('mySpaceDashboard.incomplete.missingPlace')
                            : null,
                          item.photoUrl === null
                            ? t('mySpaceDashboard.incomplete.missingPhoto')
                            : null,
                        ]
                          .filter((part): part is string => part !== null)
                          .join(' · ')}
                      </small>
                    </span>
                    <Link className="my-space-incomplete__open" to={`/articles/${item.id}`}>
                      {t('mySpaceDashboard.incomplete.open')}
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section className="my-space-section" aria-labelledby="my-space-activity-title">
            <h2 id="my-space-activity-title">{t('mySpaceDashboard.activity.title')}</h2>
            {space.activity.length === 0 ? (
              <p className="my-space-empty">{t('mySpaceDashboard.activity.empty')}</p>
            ) : (
              <ul className="my-space-activity">
                {space.activity.map((entry) => {
                  const eventText = t(`mySpaceDashboard.activity.events.${entry.action}`, {
                    actor: entry.actor,
                    object: entry.objectName,
                  })

                  return (
                    <li key={entry.id}>
                      <Activity size={18} aria-hidden="true" />
                      <div className="my-space-activity__content">
                        {entry.itemId !== null ? (
                          <Link
                            className="my-space-activity__item-link"
                            to={
                              entry.canEdit
                                ? `/articles/${entry.itemId}/modifier`
                                : `/articles/${entry.itemId}`
                            }
                          >
                            {eventText}
                          </Link>
                        ) : (
                          <p>{eventText}</p>
                        )}
                        {entry.changes.length > 0 && (
                          <small>
                            {t('mySpaceDashboard.activity.changes', {
                              fields: entry.changes
                                .map((field) => t(`mySpaceDashboard.activity.fields.${field}`))
                                .join(' · '),
                            })}
                          </small>
                        )}
                        <time dateTime={entry.createdAt}>{formatDateTime(entry.createdAt)}</time>
                      </div>
                      <button
                        type="button"
                        className="my-space-activity__dismiss"
                        aria-label={t('mySpaceDashboard.activity.dismiss', {
                          activity: eventText,
                        })}
                        title={t('mySpaceDashboard.activity.dismiss', {
                          activity: eventText,
                        })}
                        disabled={busyActivityId !== null}
                        onClick={() => void dismissActivity(entry.id)}
                      >
                        <X size={18} aria-hidden="true" />
                      </button>
                    </li>
                  )
                })}
              </ul>
            )}
          </section>

          {space.isChild && (
            <Link className="my-space-related-link" to="/enfant">
              {t('mySpaceDashboard.childHubLink')}
            </Link>
          )}
        </>
      )}

      {detail !== null && space !== null && (
        <div className="my-space-dialog-backdrop">
          <section
            className="my-space-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="my-space-dialog-title"
          >
            <div className="my-space-dialog__head">
              <h2 id="my-space-dialog-title">{t(`mySpaceDashboard.details.${detail}.title`)}</h2>
              <button
                type="button"
                className="my-space-dialog__close"
                onClick={() => setDetail(null)}
                aria-label={t('mySpaceDashboard.close')}
              >
                <X size={20} aria-hidden="true" />
              </button>
            </div>
            {detail === 'items' &&
              (space.items.length === 0 ? (
                <p className="my-space-empty">{t('mySpaceDashboard.items.empty')}</p>
              ) : (
                <ul className="my-space-list">
                  {space.items.map((item) => (
                    <li className="my-space-item" key={item.id}>
                      {item.photoUrl ? (
                        <img src={item.photoUrl} alt={item.name} />
                      ) : (
                        <span className="my-space-item__placeholder" aria-hidden="true" />
                      )}
                      <span>
                        <strong>{item.name}</strong>
                        <small>
                          {item.place || t('mySpaceDashboard.items.noPlace')}
                          {' · '}
                          {t(`items.status.${item.status}`)}
                        </small>
                      </span>
                    </li>
                  ))}
                </ul>
              ))}
            {detail === 'places' &&
              (space.places.length === 0 ? (
                <p className="my-space-empty">{t('mySpaceDashboard.places.empty')}</p>
              ) : (
                <ul className="my-space-list">
                  {space.places.map((place) => (
                    <li className="my-space-place" key={place.id}>
                      <strong>{place.name}</strong>
                      {place.rooms.length === 0 ? (
                        <small>{t('mySpaceDashboard.places.noRooms')}</small>
                      ) : (
                        <ul>
                          {place.rooms.map((room) => (
                            <li key={room.id}>
                              <span>{room.name}</span>
                              <span>
                                {t('mySpaceDashboard.places.roomCount', {
                                  count: room.itemCount,
                                })}
                              </span>
                            </li>
                          ))}
                        </ul>
                      )}
                    </li>
                  ))}
                </ul>
              ))}
            {detail === 'household' && (
              <div className="my-space-household-details">
                <section>
                  <h3>{t('mySpaceDashboard.details.household.profiles')}</h3>
                  {space.profiles.length === 0 ? (
                    <p className="my-space-empty">{t('mySpaceDashboard.profiles.empty')}</p>
                  ) : (
                    <ul className="my-space-list">
                      {space.profiles.map((profile) => (
                        <li className="my-space-row" key={profile.id}>
                          <span>
                            <strong>{profile.name}</strong>
                            <small>{t(`roles.${profile.role}`)}</small>
                          </span>
                          <span>
                            {t('mySpaceDashboard.profiles.itemCount', {
                              count: profile.itemCount,
                            })}
                          </span>
                        </li>
                      ))}
                    </ul>
                  )}
                </section>
                <section>
                  <h3>{t('mySpaceDashboard.details.household.members')}</h3>
                  {space.members.length === 0 ? (
                    <p className="my-space-empty">{t('mySpaceDashboard.members.empty')}</p>
                  ) : (
                    <ul className="my-space-list">
                      {space.members.map((member) => (
                        <li className="my-space-row" key={member.id}>
                          <span>
                            <strong>{member.name}</strong>
                            <small>
                              {t(`roles.${member.role}`)}
                              {' · '}
                              {member.lastLoginAt
                                ? formatDateTime(member.lastLoginAt)
                                : t('mySpaceDashboard.members.never')}
                            </small>
                          </span>
                        </li>
                      ))}
                    </ul>
                  )}
                </section>
              </div>
            )}
            {detail === 'loans' &&
              (space.loans.length === 0 ? (
                <p className="my-space-empty">{t('mySpaceDashboard.loans.empty')}</p>
              ) : (
                <ul className="my-space-list">
                  {space.loans.map((loan) => (
                    <li className="my-space-row" key={loan.id}>
                      <span>
                        <strong>{loan.itemName}</strong>
                        <small>
                          {t('mySpaceDashboard.loans.borrower', { name: loan.borrower })}
                          {' · '}
                          {t('mySpaceDashboard.loans.since', {
                            date: formatDateTime(loan.since),
                          })}
                        </small>
                      </span>
                      {loan.canReturn && (
                        <button
                          type="button"
                          className="btn btn-secondary"
                          disabled={busyLoanId !== null}
                          onClick={() => void returnLoan(loan.id)}
                        >
                          {busyLoanId === loan.id
                            ? t('mySpaceDashboard.loans.returning')
                            : t('mySpaceDashboard.loans.markReturned')}
                        </button>
                      )}
                    </li>
                  ))}
                </ul>
              ))}
          </section>
        </div>
      )}
    </DesignPage>
  )
}
