import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import * as family from '../api/family.ts'
import { useAuth } from '../auth/useAuth.ts'
import { DesignPage, DesignField, DesignForm, ErrorMessage } from '../components/DesignPage.tsx'
import { errorText } from '../lib/errors.ts'
import { itemText as t } from '../lib/itemCatalog.ts'

export default function FamilyAccountsPage() {
  const auth = useAuth()
  const [children, setChildren] = useState<family.ManagedChild[]>([])
  const [target, setTarget] = useState<number | undefined>()
  const [name, setName] = useState('')
  const [age, setAge] = useState(12)
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [relationship, setRelationship] = useState('mother')
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)
  const [manage, setManage] = useState<number | null>(null)
  const [reset, setReset] = useState('')
  const [parentUsername, setParentUsername] = useState('')
  const [parentRelation, setParentRelation] = useState('father')
  const load = async () => {
    setChildren((await family.listChildren()).children)
  }
  useEffect(() => {
    let live = true
    family
      .listChildren()
      .then((data) => {
        if (live) setChildren(data.children)
      })
      .catch((e) => {
        if (live) setError(errorText(e))
      })
    return () => {
      live = false
    }
  }, [auth.activeHouseholdId])
  async function run(action: () => Promise<unknown>, message: string) {
    if (busy) return
    setBusy(true)
    setError(null)
    setNotice('')
    try {
      await action()
      await load()
      await auth.refresh()
      setNotice(message)
    } catch (e) {
      setError(errorText(e))
    } finally {
      setBusy(false)
    }
  }
  return (
    <DesignPage
      frame="160:57"
      household
      title={t('Comptes enfants', 'Child accounts')}
      subtitle={t(
        'Seuls la mère et le père responsables gèrent le compte.',
        'Only the responsible mother and father manage the account.',
      )}
    >
      <ErrorMessage error={error} />
      {notice && <p role="status">{notice}</p>}
      <Link className="btn btn-secondary" to="/notifications">
        {t('Notifications et demandes à approuver', 'Notifications and approval requests')}
      </Link>
      <DesignForm
        id="child-account-setup"
        onSubmit={(e) => {
          e.preventDefault()
          void run(
            async () => {
              await family.setupChild({
                name,
                age,
                username,
                password,
                relationship,
                profileId: target,
              })
              setPassword('')
              setName('')
              setUsername('')
              setTarget(undefined)
            },
            t(
              'Compte créé. Donnez le nom d’utilisateur et le mot de passe initial à votre enfant.',
              'Account created. Give your child their username and initial password.',
            ),
          )
        }}
      >
        <h2>{t('Créer ou activer un compte', 'Create or activate an account')}</h2>
        <DesignField label={t('Enfant', 'Child')}>
          <select
            value={target ?? ''}
            onChange={(e) => {
              const id = e.target.value ? Number(e.target.value) : undefined
              setTarget(id)
              setName(children.find((child) => child.profile.id === id)?.profile.name ?? '')
            }}
          >
            <option value="">{t('Nouvel enfant', 'New child')}</option>
            {children
              .filter((child) => !child.username)
              .map((child) => (
                <option key={child.profile.id} value={child.profile.id}>
                  {child.profile.name}
                </option>
              ))}
          </select>
        </DesignField>
        <DesignField label={t('Prénom', 'Name')}>
          <input
            required
            maxLength={100}
            value={name}
            onChange={(e) => setName(e.target.value)}
            disabled={target !== undefined}
          />
        </DesignField>
        <DesignField label={t('Âge (12 à 17 ans)', 'Age (12 to 17)')}>
          <input
            required
            type="number"
            min={12}
            max={17}
            value={age}
            onChange={(e) => setAge(Number(e.target.value))}
          />
        </DesignField>
        <DesignField label={t('Je suis sa… / son…', 'I am their…')}>
          <select value={relationship} onChange={(e) => setRelationship(e.target.value)}>
            <option value="mother">{t('Mère', 'Mother')}</option>
            <option value="father">{t('Père', 'Father')}</option>
          </select>
        </DesignField>
        <DesignField label={t('Nom d’utilisateur enfant', 'Child username')}>
          <input
            required
            autoComplete="off"
            minLength={3}
            maxLength={30}
            pattern="[a-zA-Z0-9_]{3,30}"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
          />
        </DesignField>
        <DesignField
          label={t(
            'Mot de passe initial (15 caractères minimum)',
            'Initial password (at least 15 characters)',
          )}
        >
          <input
            required
            type="password"
            autoComplete="new-password"
            minLength={15}
            maxLength={128}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
        </DesignField>
        <p className="design-hint">
          {t(
            'Ce mot de passe expire après 24 h. Votre enfant choisira le sien à sa première connexion. Aucun e-mail enfant n’est nécessaire.',
            'This password expires after 24 hours. Your child chooses their own at first sign-in. No child email is needed.',
          )}
        </p>
        <button className="btn btn-primary" disabled={busy}>
          {busy ? t('Création…', 'Creating…') : t('Créer l’accès enfant', 'Create child access')}
        </button>
      </DesignForm>
      {children.map((child) => (
        <section className="family-section" key={child.profile.id}>
          <h2>{child.profile.name}</h2>
          <p>
            {child.username
              ? `${child.username} · ${child.enabled ? t('Accès actif', 'Access enabled') : t('Accès suspendu', 'Access disabled')}`
              : t('Profil sur un appareil parent', 'Profile on a parent device')}
          </p>
          {child.parents.map((parent) => (
            <div className="family-room-row" key={parent.id}>
              <span>
                {parent.name} ·{' '}
                {parent.relationship === 'mother' ? t('Mère', 'Mother') : t('Père', 'Father')} ·{' '}
                {parent.state === 'active'
                  ? t('Actif', 'Active')
                  : parent.state === 'pending'
                    ? t('Invitation en attente', 'Invitation pending')
                    : t('Retiré', 'Removed')}
              </span>
              {parent.canRevoke && parent.state !== 'revoked' && (
                <button
                  type="button"
                  className="design-link"
                  disabled={busy}
                  onClick={() =>
                    void run(
                      () => family.revokeParent(parent.id),
                      t('Accès du parent retiré.', 'Parent access removed.'),
                    )
                  }
                >
                  {t('Retirer cet accès', 'Remove access')}
                </button>
              )}
            </div>
          ))}
          {child.username && (
            <>
              <button
                type="button"
                className="btn btn-secondary"
                disabled={busy}
                onClick={() =>
                  void run(
                    () => family.setChildAccess(child.profile.id, !child.enabled),
                    t(
                      'Accès mis à jour. Les anciennes sessions sont fermées.',
                      'Access updated. Previous sessions are closed.',
                    ),
                  )
                }
              >
                {child.enabled
                  ? t('Suspendre la connexion', 'Disable sign-in')
                  : t('Réactiver la connexion', 'Enable sign-in')}
              </button>
              <Link className="design-link" to={`/enfant?enfant=${child.profile.id}`}>
                {t('Gérer ses objets et collections', 'Manage their belongings and collections')}
              </Link>
              <button
                type="button"
                className="design-link"
                onClick={() => {
                  setManage(manage === child.profile.id ? null : child.profile.id)
                  setReset('')
                }}
              >
                {t('Mot de passe et deuxième parent', 'Password and second parent')}
              </button>
            </>
          )}
          {manage === child.profile.id && (
            <>
              <DesignForm
                id={`reset-${child.profile.id}`}
                onSubmit={(e) => {
                  e.preventDefault()
                  void run(
                    async () => {
                      await family.resetChildPassword(child.profile.id, reset)
                      setReset('')
                    },
                    t(
                      'Mot de passe réinitialisé. Donnez le nouveau à votre enfant.',
                      'Password reset. Give the new one to your child.',
                    ),
                  )
                }}
              >
                <DesignField label={t('Nouveau mot de passe initial', 'New initial password')}>
                  <input
                    required
                    type="password"
                    autoComplete="new-password"
                    minLength={15}
                    maxLength={128}
                    value={reset}
                    onChange={(e) => setReset(e.target.value)}
                  />
                </DesignField>
                <button className="btn btn-secondary" disabled={busy}>
                  {t('Réinitialiser et fermer ses sessions', 'Reset and close their sessions')}
                </button>
              </DesignForm>
              <DesignForm
                id={`invite-${child.profile.id}`}
                onSubmit={(e) => {
                  e.preventDefault()
                  void run(
                    () => family.inviteParent(child.profile.id, parentUsername, parentRelation),
                    t(
                      'Invitation envoyée dans les Notifications de l’autre parent.',
                      'Invitation sent to the other parent’s Notifications.',
                    ),
                  )
                }}
              >
                <DesignField
                  label={t('Nom d’utilisateur de l’autre parent', 'Other parent’s username')}
                >
                  <input
                    required
                    value={parentUsername}
                    onChange={(e) => setParentUsername(e.target.value)}
                  />
                </DesignField>
                <DesignField label={t('Ce parent est sa… / son…', 'This parent is their…')}>
                  <select
                    value={parentRelation}
                    onChange={(e) => setParentRelation(e.target.value)}
                  >
                    <option value="mother">{t('Mère', 'Mother')}</option>
                    <option value="father">{t('Père', 'Father')}</option>
                  </select>
                </DesignField>
                <button className="btn btn-secondary" disabled={busy}>
                  {t('Inviter ce parent', 'Invite this parent')}
                </button>
              </DesignForm>
            </>
          )}
        </section>
      ))}
      <Link className="design-link" to="/foyer/membres">
        {t('Revenir aux membres du foyer', 'Back to household members')}
      </Link>
    </DesignPage>
  )
}
