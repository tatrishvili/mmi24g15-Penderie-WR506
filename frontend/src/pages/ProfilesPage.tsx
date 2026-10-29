import { useState } from 'react'
import type { FormEvent } from 'react'
import { Check, LogOut, Plus } from 'lucide-react'
import { ApiError } from '../api/client.ts'
import { useAuth } from '../auth/useAuth.ts'
import { getCopy } from '../i18n/copy.ts'
import { roleLabel } from '../types/auth.ts'

export default function ProfilesPage() {
  const copy = getCopy()
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
          error instanceof ApiError ? error.message : 'Une erreur inattendue est survenue.',
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
      setPinError(error instanceof ApiError ? error.message : 'Une erreur inattendue est survenue.')
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
        error instanceof ApiError ? error.message : 'Une erreur inattendue est survenue.',
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
        error instanceof ApiError ? error.message : 'Une erreur inattendue est survenue.',
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
        error instanceof ApiError ? error.message : 'Une erreur inattendue est survenue.',
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
        error instanceof ApiError ? error.message : 'Une erreur inattendue est survenue.',
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
          'Créez votre foyer pour commencer.'
        ) : (
          <>
            Foyer « {household.name} » — code : <strong>{household.slug}</strong>
          </>
        )}
      </p>

      <div className="auth-card">
        {household === null ? (
          households.length > 0 && !showCreateForm ? (
            <>
              <h1>Choisissez un foyer</h1>
              <p className="auth-sub">
                Vous avez plusieurs foyers. Choisissez celui à ouvrir.
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
                        <span className="profile-role">code : {candidate.slug}</span>
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
                Créer un autre foyer
              </button>
            </>
          ) : (
          <>
            <h1>Créez votre foyer</h1>
            <p className="auth-sub">
              Votre compte est créé. Ajoutez votre prénom et votre foyer pour devenir le profil
              adulte, puis vous pourrez ajouter enfants et conjoint.
            </p>

            {households.length > 0 && (
              <button
                type="button"
                className="btn btn-secondary"
                onClick={() => setShowCreateForm(false)}
                style={{ marginBottom: 12 }}
              >
                Retour aux foyers
              </button>
            )}

            <form className="auth-form" onSubmit={(event) => void onCreateHousehold(event)} noValidate>
              {householdError !== null && (
                <p role="alert" className="form-error">
                  {householdError}
                </p>
              )}

              <div className="field">
                <label htmlFor="household-firstname">Votre prénom</label>
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
                <label htmlFor="household-name">Nom du foyer</label>
                <input
                  id="household-name"
                  className="input"
                  type="text"
                  placeholder="Famille Moreau"
                  value={householdName}
                  onChange={(event) => setHouseholdName(event.target.value)}
                  required
                  minLength={2}
                />
              </div>

              <div className="field">
                <label htmlFor="household-place">Lieu principal (optionnel)</label>
                <input
                  id="household-place"
                  className="input"
                  type="text"
                  placeholder="Maison"
                  value={placeName}
                  onChange={(event) => setPlaceName(event.target.value)}
                />
              </div>

              <button type="submit" className="btn btn-primary" disabled={creatingHousehold}>
                {creatingHousehold ? 'Création…' : 'Créer mon foyer'}
              </button>
            </form>
          </>
          )
        ) : (
          <>
            <h1>Qui entre ?</h1>

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
                    {profile.hasPin ? ' · code PIN' : ''}
                  </span>
                </span>
                {profile.id === activeProfileId && (
                  <Check className="profile-check" size={20} aria-label="Profil actif" />
                )}
              </button>

              {pinFor === profile.id && profile.id !== activeProfileId && (
                <form
                  className="pin-pad"
                  onSubmit={(event) => void onPinSubmit(event, profile.id)}
                  noValidate
                >
                  <div className="field">
                    <label htmlFor={`pin-${profile.id}`}>Code PIN de {profile.name}</label>
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
                    {busyId === profile.id ? 'Vérification…' : 'Entrer'}
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
                <Plus size={14} aria-hidden="true" /> Enfant
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
                <Plus size={14} aria-hidden="true" /> Conjoint
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
                  <label htmlFor="child-name">Prénom de l’enfant</label>
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
                  <label htmlFor="child-pin">{copy.profiles.pinLabel}</label>
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
                  {copy.profiles.pinNote}
                </p>
                <button type="submit" className="btn btn-secondary" disabled={creating}>
                  {creating ? 'Création…' : 'Ajouter l’enfant'}
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
                  <label htmlFor="partner-name">Prénom du conjoint</label>
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
                  <label htmlFor="partner-email">E-mail du conjoint</label>
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
                  <label htmlFor="partner-password">Mot de passe (8 caractères minimum)</label>
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
                  {creating ? 'Création…' : 'Ajouter le conjoint'}
                </button>
              </form>
            )}
          </>
        )}
          </>
        )}

        <div style={{ marginTop: 20 }}>
          <button type="button" className="btn btn-secondary" onClick={() => void logout()}>
            <LogOut size={18} aria-hidden="true" /> Se déconnecter
          </button>
        </div>
      </div>
    </div>
  )
}
