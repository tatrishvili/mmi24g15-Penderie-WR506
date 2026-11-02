import { audienceLabel } from '../lib/collectionAudience.ts'
import type { CollectionAudience as Audience } from '../api/social.ts'
import { itemText as t } from '../lib/itemCatalog.ts'
import { DesignField } from './DesignPage.tsx'

export function CollectionAudience({
  value,
  onChange,
  disabled = false,
}: {
  value: Audience
  onChange: (value: Audience) => void
  disabled?: boolean
}) {
  return (
    <DesignField label={t('Visibilité', 'Visibility')}>
      <select
        value={value}
        disabled={disabled}
        onChange={(e) => onChange(e.target.value as Audience)}
      >
        {(['personal', 'friends', 'followers', 'app'] as const).map((audience) => (
          <option key={audience} value={audience}>
            {audienceLabel(audience)}
          </option>
        ))}
      </select>
    </DesignField>
  )
}
export function CollectionApproval({
  checked,
  onChange,
  disabled = false,
}: {
  checked: boolean
  onChange: (checked: boolean) => void
  disabled?: boolean
}) {
  return (
    <label className="collection-approval">
      <input
        type="checkbox"
        checked={checked}
        disabled={disabled}
        onChange={(e) => onChange(e.target.checked)}
      />
      <span>
        {t(
          'J’approuve le partage du nom, de la couverture et des articles affichés dans cet aperçu.',
          'I approve sharing the name, cover and items shown in this preview.',
        )}
      </span>
    </label>
  )
}
