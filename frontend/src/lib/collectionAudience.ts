import type { CollectionAudience as Audience } from '../api/social.ts'
import { itemText as t, isItemType, itemTypeLabel } from './itemCatalog.ts'

export const audienceLabel = (audience: Audience) =>
  ({
    personal: t('Privée', 'Private'),
    friends: t('Amis', 'Friends'),
    followers: t('Abonnés', 'Followers'),
    app: t('Tout Penderie', 'Everyone on Penderie'),
  })[audience]

export const collectionItemDetail = (item: { type: string | null; color: string | null }) =>
  [item.type && (isItemType(item.type) ? itemTypeLabel(item.type) : item.type), item.color]
    .filter(Boolean)
    .join(' · ')
