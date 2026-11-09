import { t } from 'i18next'

const labels = {
  pull: ['Pull', 'Sweater'],
  tshirt: ['T-shirt', 'T-shirt'],
  chemise: ['Chemise', 'Shirt'],
  blouse: ['Blouse', 'Blouse'],
  polo: ['Polo', 'Polo shirt'],
  debardeur: ['Débardeur', 'Tank top'],
  sweat: ['Sweat', 'Sweatshirt'],
  gilet: ['Gilet', 'Cardigan'],
  veste: ['Veste', 'Jacket'],
  manteau: ['Manteau', 'Coat'],
  pantalon: ['Pantalon', 'Trousers'],
  jean: ['Jean', 'Jeans'],
  short: ['Short', 'Shorts'],
  jupe: ['Jupe', 'Skirt'],
  robe: ['Robe', 'Dress'],
  combinaison: ['Combinaison', 'Jumpsuit'],
  costume: ['Costume', 'Suit'],
  sous_vetement: ['Sous-vêtement', 'Underwear'],
  chaussettes: ['Chaussettes', 'Socks'],
  pyjama: ['Pyjama', 'Pyjamas'],
  maillot: ['Maillot de bain', 'Swimwear'],
  sport: ['Tenue de sport', 'Sportswear'],
  chaussure: ['Chaussures', 'Shoes'],
  sac: ['Sac', 'Bag'],
  ceinture: ['Ceinture', 'Belt'],
  chapeau: ['Chapeau / bonnet', 'Hat'],
  echarpe: ['Écharpe', 'Scarf'],
  gants: ['Gants', 'Gloves'],
  bijou: ['Bijou / montre', 'Jewellery / watch'],
  livre: ['Livre', 'Book'],
  vinyle: ['Vinyle', 'Vinyl record'],
  cd: ['CD', 'CD'],
  decoration: ['Décoration', 'Decoration'],
  electronique: ['Électronique', 'Electronics'],
  meuble: ['Meuble', 'Furniture'],
  jouet: ['Jouet', 'Toy'],
  autre: ['Autre objet', 'Other object'],
} as const
export type ItemType = keyof typeof labels
export const ITEM_TYPES = Object.keys(labels) as ItemType[]
export const isItemType = (value: string): value is ItemType => value in labels
export const isBook = (type: string) => type === 'livre'
export const isMusic = (type: string) => type === 'vinyle' || type === 'cd'
export const isClothing = (type: string) =>
  !['livre', 'vinyle', 'cd', 'decoration', 'electronique', 'meuble', 'jouet', 'autre'].includes(
    type,
  )

export const itemText = (type: ItemType) => t(`itemTypes.${type}`) || labels[type][0]
export const itemTypeLabel = (type: ItemType) => t(`itemTypes.${type}`) || labels[type][0]

/**
 * Get a translation for a general key, with fallback to the key itself.
 * Used by components like AppNav that use i18next keys directly.
 * When i18next is not available, returns the key or the default value.
 */
export const getCopy = (key: string, defaultValue?: string): string => {
  const translation = t(key)
  if (translation !== undefined && translation !== '') return translation
  return defaultValue ?? key
}