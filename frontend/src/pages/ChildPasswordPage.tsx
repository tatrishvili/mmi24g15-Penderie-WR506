import { useState } from 'react'
import { changeChildPassword } from '../api/family.ts'
import { useAuth } from '../auth/useAuth.ts'
import { DesignPage, DesignForm, DesignField, ErrorMessage } from '../components/DesignPage.tsx'
import { errorText } from '../lib/errors.ts'
import { itemText as t } from '../lib/itemCatalog.ts'

export default function ChildPasswordPage() {
  const auth = useAuth()
  const [initial, setInitial] = useState('')
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  return (
    <DesignPage
      frame="160:57"
      title={t('Choisis ton mot de passe', 'Choose your password')}
      subtitle={t(
        'Utilise le mot de passe donné par ton parent pour choisir le tien.',
        'Use the password your parent gave you to choose your own.',
      )}
    >
      <ErrorMessage error={error} />
      <DesignForm
        id="child-password"
        onSubmit={(e) => {
          e.preventDefault()
          if (busy) return
          if (password !== confirmation) {
            setError(t('Les mots de passe ne correspondent pas.', 'The passwords do not match.'))
            return
          }
          setBusy(true)
          setError(null)
          void changeChildPassword(initial, password)
            .then(() => auth.refresh())
            .catch((e) => setError(errorText(e)))
            .finally(() => setBusy(false))
        }}
      >
        <DesignField label={t('Mot de passe initial', 'Initial password')}>
          <input
            required
            type="password"
            autoComplete="current-password"
            value={initial}
            onChange={(e) => setInitial(e.target.value)}
          />
        </DesignField>
        <DesignField
          label={t(
            'Ton nouveau mot de passe (15 caractères minimum)',
            'Your new password (at least 15 characters)',
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
        <DesignField label={t('Répète ton nouveau mot de passe', 'Repeat your new password')}>
          <input
            required
            type="password"
            autoComplete="new-password"
            minLength={15}
            value={confirmation}
            onChange={(e) => setConfirmation(e.target.value)}
          />
        </DesignField>
        <p>
          {t(
            'Tu te reconnecteras ensuite avec ton nouveau mot de passe.',
            'Then sign in with your new password.',
          )}
        </p>
        <button className="btn btn-primary" disabled={busy}>
          {busy
            ? t('Enregistrement…', 'Saving…')
            : t('Enregistrer mon mot de passe', 'Save my password')}
        </button>
      </DesignForm>
      <button type="button" className="design-link" onClick={() => void auth.logout()}>
        {t('Se déconnecter', 'Sign out')}
      </button>
    </DesignPage>
  )
}
