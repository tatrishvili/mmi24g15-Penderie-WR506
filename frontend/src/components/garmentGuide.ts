export type GarmentType = 'tshirt' | 'pantalon' | 'jupe' | 'autre'

export const GARMENT_TYPES: { value: GarmentType; label: string }[] = [
  { value: 'tshirt', label: 'T-shirt' },
  { value: 'pantalon', label: 'Pantalon' },
  { value: 'jupe', label: 'Jupe' },
  { value: 'autre', label: 'Objet' },
]
