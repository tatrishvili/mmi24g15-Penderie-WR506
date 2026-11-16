import { useState } from 'react'
import type { FormEvent } from 'react'
import { Check, LogOut, Plus } from 'lucide-react'
import { ApiError } from '../api/client.ts'
import { useAuth } from '../auth/useAuth.ts'
import { t } from '../i18n/copy.ts'
import { roleLabel } from '../types/auth.ts'
import { itemText as tr } from '../lib/itemCatalog.ts'

export default function ProfilesPage() {
  const {
    households,
    household,
    profiles,
    activeProfileId,
    activeProfile,
    switchProfile,
    unlockProfileWithPin,
    createProfile,
    createHousehold,
    selectHousehold,
    logout,
  } = useAuth()

  const [pinFor, setPinFor] = useState<number | null>(null)
  const [pin, setPin] = useState('')
  const [pinError, setPinError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)

  const [showChildForm, setShowChildForm] = useState(false)
  const [showPartnerForm, setShowPartnerForm] = useState(false)
  const [childName, setChildName] = useState('')
  const [childPin, setChildPin] = useState('')
  const [partnerName, setPartnerName] = useState('')
  const [partnerEmail, setPartnerEmail] = useState('')
  const [partnerPassword, setPartnerPassword] = useState('')
  const [createError, setCreateError] = useState<string | null>(null)
  const [creating, setCreating] = useState(false)

  const [householdName, setHouseholdName] = useState('')
  const [firstName, setFirstName] = useState('')
  const [placeName, setPlaceName] = useState('')
  const [householdError, setHouseholdError] = useState<string | null>(null)
  const [creatingHousehold, setCreatingHousehold] = useState(false)
  const [showCreateForm, setShowCreateForm] = useState(false)
  const [selectingId, setSelectingId] = useState<number | null>(null)
  const [selectError, setSelectError] = useState<string | null>(null)

  const canManage = activeProfile === null || activeProfile.role !== 'child'

  async function onSelect(profileId: number, hasPin: boolean) {
    if (profileId === activeProfileId || busyId !== null) return

    if (!hasPin) {
      setBusyId(profileId)
      setPinError(null)

      try {
        await switchProfile(profileId)
      } catch (error) {
        setPinError(
          error instanceof ApiError ? error.message : t('unexpectedError'),
        )
      } finally {
        setBusyId(null)
      }

      return
    }

    setPinFor(pinFor === profileId ? null : profileId)
    setPin('')
    setPinError(null)
  }

  async function onPinSubmit(event: FormEvent, profileId: number) {
    event.preventDefault()

    setPinError(null)
    setBusyId(profileId)

    try {
      await unlockProfileWithPin(profileId, pin)
      setPinFor(null)
      setPin('')
    } catch (error) {
      setPinError(error instanceof ApiError ? error.message : t('unexpectedError'))
    } finally {
      setBusyId(null)
    }
  }

  async function onCreateChild(event: FormEvent) {
    event.preventDefault()
    setCreateError(null)
    setCreating(true)

    try {
      await createProfile({ role: 'child', name: childName.trim(), pin: childPin })
      setChildName('')
      setChildPin('')
      setShowChildForm(false)
    } catch (error) {
      setCreateError(
        error instanceof ApiError ? error.message : t('unexpectedError'),
      )
    } finally {
      setCreating(false)
    }
  }

  async function onCreateHousehold(event: FormEvent) {
    event.preventDefault()
    setHouseholdError(null)
    setCreatingHousehold(true)

    try {
      await createHousehold({
        householdName: householdName.trim(),
        firstName: firstName.trim(),
        placeName: placeName.trim() === '' ? undefined : placeName.trim(),
      })
      setHouseholdName('')
      setFirstName('')
      setPlaceName('')
    } catch (error) {
      setHouseholdError(
        error instanceof ApiError ? error.message : t('unexpectedError'),
      )
    } finally {
      setCreatingHousehold(false)
    }
  }

  async function onSelectHousehold(householdId: number) {
    setSelectError(null)
    setSelectingId(householdId)

    try {
      await selectHousehold(householdId)
    } catch (error) {
      setSelectError(
        error instanceof ApiError ? error.message : t('unexpectedError'),
      )
    } finally {
      setSelectingId(null)
    }
  }

  async function onCreatePartner(event: FormEvent) {
    event.preventDefault()
    setCreateError(null)
    setCreating(true)

    try {
      await createProfile({
        role: 'partner',
        name: partnerName.trim(),
        email: partnerEmail.trim(),
        password: partnerPassword,
      })
      setPartnerName('')
      setPartnerEmail('')
      setPartnerPassword('')
      setShowPartnerForm(false)
    } catch (error) {
      setCreateError(
        error instanceof ApiError ? error.message : t('unexpectedError'),
      )
    } finally {
      setCreating(false)
    }
  }

  return (
    <div className="auth-page">
      <span className="auth-brand">Penderie</span>
      <p className="auth-tagline">
        {household === null ? (
          tr('Créez votre foyer pour commencer.', 'Create your household to get started.')
        ) : (
          <>
            {tr('Foyer', 'Household')} « {household.name} » — {tr('code', 'code')} : <strong>{household.slug}</strong>
          </>
        )}
      </p>

      <div className="auth-card">
        {household === null ? (
          households.length > 0 && !showCreateForm ? (
            <>
              <h1>{tr('Choisissez un foyer', 'Choose a household')}</h1>
              <p className="auth-sub">
                {tr('Vous avez plusieurs foyers. Choisissez celui à ouvrir.', 'You have several households. Choose the one to open.')}
              </p>

              {selectError !== null && (
                <p role="alert" className="form-error">
                  {selectError}
                </p>
              )}

              <ul className="profile-list">
                {households.map((candidate) => (
                  <li key={candidate.id}>
                    <button
                      type="button"
                      className="profile-row"
                      onClick={() => void onSelectHousehold(candidate.id)}
                      disabled={selectingId !== null}
                    >
                      <span className="avatar" aria-hidden="true">
                        {candidate.name.charAt(0).toUpperCase()}
                      </span>
                      <span className="profile-meta">
                        <span className="profile-name">{candidate.name}</span>
                        <br />
                        <span className="profile-role">{tr('code', 'code')} : {candidate.slug}</span>
                      </span>
                    </button>
                  </li>
                ))}
              </ul>

              <button
                type="button"
                className="btn btn-secondary"
                onClick={() => setShowCreateForm(true)}
              >
                {tr('Créer un autre foyer', 'Create another household')}
              </button>
            </>
          ) : (
          <>
            <h1>{tr('Créez votre foyer', 'Create your household')}</h1>
            <p className="auth-sub">
              {tr('Votre compte est créé. Ajoutez votre prénom et votre foyer pour devenir le profil adulte, puis vous pourrez ajouter enfants et conjoint.', 'Your account is created. Add your first name and household to become the adult profile, then you can add children and a partner.')}
            </p>

            {households.length > 0 && (
              <button
                type="button"
                className="btn btn-secondary"
                onClick={() => setShowCreateForm(false)}
                style={{ marginBottom: 12 }}
              >
                {tr('Retour aux foyers', 'Back to households')}
              </button>
            )}

            <form className="auth-form" onSubmit={(event) => void onCreateHousehold(event)} noValidate>
              {householdError !== null && (
                <p role="alert" className="form-error">
                  {householdError}
                </p>
              )}

              <div className="field">
                <label htmlFor="household-firstname">{tr('Votre prénom', 'Your first name')}</label>
                <input
                  id="household-firstname"
                  className="input"
                  type="text"
                  autoComplete="given-name"
                  value={firstName}
                  onChange={(event) => setFirstName(event.target.value)}
                  required
                />
              </div>

              <div className="field">
                <label htmlFor="household-name">{tr('Nom du foyer', 'Household name')}</label>
                <input
                  id="household-name"
                  className="input"
                  type="text"
                  placeholder={tr('Famille Moreau', 'The Moreau family')}
                  value={householdName}
                  onChange={(event) => setHouseholdName(event.target.value)}
                  required
                  minLength={2}
                />
              </div>

              <div className="field">
                <label htmlFor="household-place">{tr('Lieu principal (optionnel)', 'Main place (optional)')}</label>
                <input
                  id="household-place"
                  className="input"
                  type="text"
                  placeholder={tr('Maison', 'House')}
                  value={placeName}
                  onChange={(event) => setPlaceName(event.target.value)}
                />
              </div>

              <button type="submit" className="btn btn-primary" disabled={creatingHousehold}>
                {creatingHousehold ? tr('Création…', 'Creating…') : tr('Créer mon foyer', 'Create my household')}
              </button>
            </form>
          </>
          )
        ) : (
          <>
            <h1>{tr('Qui entre ?', 'Who is signing in?')}</h1>

        {pinError !== null && (
          <p role="alert" className="form-error" style={{ marginTop: 12 }}>
            {pinError}
          </p>
        )}

        <ul className="profile-list">
          {profiles.map((profile) => (
            <li key={profile.id}>
              <button
                type="button"
                className={
                  profile.id === activeProfileId ? 'profile-row profile-row--active' : 'profile-row'
                }
                onClick={() => void onSelect(profile.id, profile.hasPin)}
                disabled={busyId !== null}
                aria-pressed={profile.id === activeProfileId}
              >
                <span className="avatar" aria-hidden="true">
                  {profile.name.charAt(0).toUpperCase()}
                </span>
                <span className="profile-meta">
                  <span className="profile-name">{profile.name}</span>
                  <br />
                  <span className="profile-role">
                    {roleLabel(profile.role)}
                    {profile.hasPin ? ` · ${tr('code PIN', 'PIN')}` : ''}
                  </span>
                </span>
                {profile.id === activeProfileId && (
                  <Check className="profile-check" size={20} aria-label={tr('Profil actif', 'Active profile')} />
                )}
              </button>

              {pinFor === profile.id && profile.id !== activeProfileId && (
                <form
                  className="pin-pad"
                  onSubmit={(event) => void onPinSubmit(event, profile.id)}
                  noValidate
                >
                  <div className="field">
                    <label htmlFor={`pin-${profile.id}`}>{tr('Code PIN de', 'PIN for')} {profile.name}</label>
                    <input
                      id={`pin-${profile.id}`}
                      className="input input--pin"
                      type="password"
                      inputMode="numeric"
                      pattern="[0-9]*"
                      autoComplete="off"
                      maxLength={6}
                      value={pin}
                      onChange={(event) => setPin(event.target.value.replace(/[^0-9]/g, ''))}
                      required
                    />
                  </div>
                  <button type="submit" className="btn btn-primary" disabled={busyId !== null}>
                    {busyId === profile.id ? tr('Vérification…', 'Checking…') : tr('Entrer', 'Enter')}
                  </button>
                </form>
              )}
            </li>
          ))}
        </ul>

        {canManage && (
          <>
            <div className="steps">
              <button
                type="button"
                className={showChildForm ? 'step step--current' : 'step'}
                onClick={() => {
                  setShowChildForm(!showChildForm)
                  setShowPartnerForm(false)
                  setCreateError(null)
                }}
              >
                <Plus size={14} aria-hidden="true" /> {tr('Enfant', 'Child')}
              </button>
              <button
                type="button"
                className={showPartnerForm ? 'step step--current' : 'step'}
                onClick={() => {
                  setShowPartnerForm(!showPartnerForm)
                  setShowChildForm(false)
                  setCreateError(null)
                }}
              >
                <Plus size={14} aria-hidden="true" /> {tr('Conjoint', 'Partner')}
              </button>
            </div>

            {createError !== null && (
              <p role="alert" className="form-error" style={{ marginTop: 12 }}>
                {createError}
              </p>
            )}

            {showChildForm && (
              <form
                className="auth-form"
                onSubmit={(event) => void onCreateChild(event)}
                noValidate
              >
                <div className="field">
                  <label htmlFor="child-name">{tr('Prénom de l’enfant', 'Child’s first name')}</label>
                  <input
                    id="child-name"
                    className="input"
                    type="text"
                    value={childName}
                    onChange={(event) => setChildName(event.target.value)}
                    required
                  />
                </div>
                <div className="field">
                  <label htmlFor="child-pin">{t('profiles.pinLabel')}</label>
                  <input
                    id="child-pin"
                    className="input input--pin"
                    type="password"
                    inputMode="numeric"
                    pattern="[0-9]*"
                    autoComplete="off"
                    maxLength={6}
                    value={childPin}
                    onChange={(event) => setChildPin(event.target.value.replace(/[^0-9]/g, ''))}
                  />
                </div>
<p className="auth-sub">
              {t('profiles.pinNote')}
            </p>
                <button type="submit" className="btn btn-secondary" disabled={creating}>
                  {creating ? tr('Création…', 'Creating…') : tr('Ajouter l’enfant', 'Add child')}
                </button>
              </form>
            )}

            {showPartnerForm && (
              <form
                className="auth-form"
                onSubmit={(event) => void onCreatePartner(event)}
                noValidate
              >
                <div className="field">
                  <label htmlFor="partner-name">{tr('Prénom du conjoint', 'Partner’s first name')}</label>
                  <input
                    id="partner-name"
                    className="input"
                    type="text"
                    value={partnerName}
                    onChange={(event) => setPartnerName(event.target.value)}
                    required
                  />
                </div>
                <div className="field">
                  <label htmlFor="partner-email">{tr('E-mail du conjoint', 'Partner’s e-mail')}</label>
                  <input
                    id="partner-email"
                    className="input"
                    type="email"
                    autoComplete="email"
                    value={partnerEmail}
                    onChange={(event) => setPartnerEmail(event.target.value)}
                    required
                  />
                </div>
                <div className="field">
                  <label htmlFor="partner-password">{tr('Mot de passe (8 caractères minimum)', 'Password (at least 8 characters)')}</label>
                  <input
                    id="partner-password"
                    className="input"
                    type="password"
                    autoComplete="new-password"
                    value={partnerPassword}
                    onChange={(event) => setPartnerPassword(event.target.value)}
                    required
                    minLength={8}
                  />
                </div>
                <button type="submit" className="btn btn-secondary" disabled={creating}>
                  {creating ? tr('Création…', 'Creating…') : tr('Ajouter le conjoint', 'Add partner')}
                </button>
              </form>
            )}
          </>
        )}
          </>
        )}

        <div style={{ marginTop: 20 }}>
          <button type="button" className="btn btn-secondary" onClick={() => void logout()}>
            <LogOut size={18} aria-hidden="true" /> {tr('Se déconnecter', 'Sign out')}
          </button>
        </div>
      </div>
    </div>
  )
}
