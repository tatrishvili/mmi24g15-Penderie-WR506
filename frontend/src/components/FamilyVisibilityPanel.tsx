import ChildSnapshotPreview from './ChildSnapshotPreview.tsx'
import { useEffect, useState } from 'react'
import * as family from '../api/family.ts'
import { useAuth } from '../auth/useAuth.ts'
import type { ItemSummary } from '../types/item.ts'
import { DesignForm, ErrorMessage } from './DesignPage.tsx'
import { itemText as t } from '../lib/itemCatalog.ts'
import { errorText } from '../lib/errors.ts'

export default function FamilyVisibilityPanel({
  item,
  onSave,
}: {
  item: ItemSummary
  onSave: (item: ItemSummary) => void
}) {
  const { activeProfile } = useAuth()
  const child = activeProfile?.role === 'child'
  const [members, setMembers] = useState<family.FamilyMember[]>([])
  const [audience, setAudience] = useState(
    item.familyAudience === 'legacy' ? 'household' : (item.familyAudience ?? 'owner'),
  )
  const [ids, setIds] = useState(item.familyViewerIds ?? [])
  const [approved, setApproved] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState('')
  useEffect(() => {
    let live = true
    family
      .familyMembers()
      .then((data) => {
        if (live) setMembers(data.members)
      })
      .catch((e) => {
        if (live) setError(errorText(e))
      })
    return () => {
      live = false
    }
  }, [item.id])
  return (
    <DesignForm
      id="family-visibility"
      onSubmit={(e) => {
        e.preventDefault()
        if (busy) return
        setBusy(true)
        setError(null)
        setNotice('')
        void family
          .familyAudience(item.id, {
            audience,
            profileIds: ids,
            version: item.sharingVersion ?? 1,
            approved: approved || audience === 'owner',
          })
          .then((result) => {
            onSave(result.item)
            setApproved(false)
            setNotice(
              result.approval
                ? t(
                    'Demande envoyée à tes parents. Rien n’est visible avant leur accord.',
                    'Request sent to your parents. Nothing becomes visible before approval.',
                  )
                : t('Visibilité enregistrée.', 'Visibility saved.'),
            )
          })
          .catch((e) => setError(errorText(e)))
          .finally(() => setBusy(false))
      }}
    >
      <h2>
        {t('Qui peut voir cet objet dans le foyer ?', 'Who can see this object in the household?')}
      </h2>
      <ErrorMessage error={error} />
      {notice && <p role="status">{notice}</p>}
      <fieldset>
        <legend>{t('Choisir une audience', 'Choose an audience')}</legend>
        {(
          [
            ['owner', t('Moi uniquement', 'Only me')],
            ['selected', t('Personnes choisies', 'Selected people')],
            ['household', t('Tout le foyer', 'Everyone in the household')],
          ] as const
        ).map(([value, label]) => (
          <label className="family-choice" key={value}>
            <input
              type="radio"
              name="family-audience"
              value={value}
              checked={audience === value}
              onChange={() => {
                setAudience(value)
                setApproved(false)
              }}
            />
            {label}
          </label>
        ))}
      </fieldset>
      {audience === 'selected' && (
        <fieldset>
          <legend>{t('Personnes autorisées', 'Allowed people')}</legend>
          {members
            .filter((member) => member.id !== item.ownerProfile.id)
            .map((member) => (
              <label className="family-choice" key={member.id}>
                <input
                  type="checkbox"
                  checked={ids.includes(member.id)}
                  onChange={(e) => {
                    setIds((values) =>
                      e.target.checked
                        ? [...values, member.id]
                        : values.filter((id) => id !== member.id),
                    )
                    setApproved(false)
                  }}
                />
                {member.name}
              </label>
            ))}
        </fieldset>
      )}
      {audience === 'household' && (
        <p>
          {t('Personnes incluses dans cet accord', 'People included in this approval')} :{' '}
          {members.map((member) => member.name).join(', ')}.{' '}
          {t(
            'Les nouveaux membres ne recevront pas automatiquement cet accès.',
            'New members will not automatically receive access.',
          )}
        </p>
      )}
      {audience !== 'owner' && (
        <div className="family-preview">
          <ChildSnapshotPreview item={item} />
          <p>
            {t(
              'Les notes, souvenirs, codes QR et emplacements restent cachés. Cet accès permet seulement de consulter.',
              'Notes, memories, QR codes and locations stay hidden. This grants viewing only.',
            )}
          </p>
          {!child && (
            <label className="family-choice">
              <input
                type="checkbox"
                checked={approved}
                onChange={(e) => setApproved(e.target.checked)}
              />
              {t(
                'J’approuve cet aperçu pour les personnes choisies.',
                'I approve this preview for the chosen people.',
              )}
            </label>
          )}
        </div>
      )}
      <p className="design-hint">
        {t(
          'Les parents responsables peuvent toujours gérer les objets de leur enfant. Une modification après partage demande une nouvelle validation.',
          'Responsible parents can always manage their child’s belongings. Changes after sharing need a new review.',
        )}
      </p>
      <button
        className="btn btn-primary"
        disabled={
          busy ||
          (audience === 'selected' && ids.length === 0) ||
          (audience !== 'owner' && !child && !approved)
        }
      >
        {busy
          ? t('Enregistrement…', 'Saving…')
          : audience === 'owner'
            ? t('Masquer cet objet aux autres', 'Hide this object from others')
            : child
              ? t('Demander l’accord de mes parents', 'Ask my parents for approval')
              : t('Enregistrer la visibilité', 'Save visibility')}
      </button>
    </DesignForm>
  )
}
