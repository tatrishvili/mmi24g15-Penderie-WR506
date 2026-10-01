import { useState } from 'react'
import type { FormEvent } from 'react'
import { Check, LogOut, Plus } from 'lucide-react'
import { ApiError } from '../api/client.ts'
import { useAuth } from '../auth/useAuth.ts'
import { roleLabel } from '../types/auth.ts'

export default function ProfilesPage() {
  const {
    household,
    profiles,
    activeProfileId,
    activeProfile,
    switchProfile,
    pinLogin,
    createProfile,
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

    if (household === null) return

    setPinError(null)
    setBusyId(profileId)

    try {
      await pinLogin(household.slug, profileId, pin)
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
        Foyer « {household?.name} » — code : <strong>{household?.slug}</strong>
      </p>

      <div className="auth-card">
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
                  <label htmlFor="child-pin">Code PIN (4 à 6 chiffres)</label>
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
                    required
                  />
                </div>
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

        <div style={{ marginTop: 20 }}>
          <button type="button" className="btn btn-secondary" onClick={() => void logout()}>
            <LogOut size={18} aria-hidden="true" /> Se déconnecter
          </button>
        </div>
      </div>
    </div>
  )
}
