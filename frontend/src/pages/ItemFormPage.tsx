import {
  ITEM_TYPES,
  isItemType,
  itemTypeLabel,
  itemText,
  isBook,
  isMusic,
  isClothing,
} from '../lib/itemCatalog.ts'
import type { ItemType } from '../lib/itemCatalog.ts'
import FigmaGlyph from '../components/FigmaGlyph.tsx'
import itemSymbol from '../images/figma/item-symbol.svg'
import { errorText } from '../lib/errors.ts'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../auth/useAuth.ts'
import { resizeToBase64 } from '../lib/image.ts'
import { listPlaces } from '../api/places.ts'
import type { Place } from '../types/location.ts'
import { flattenStorageLocations } from '../types/location.ts'
import type { ItemCondition } from '../types/item.ts'
import {
  DesignPage,
  DesignField,
  DesignForm,
  DesignNotice,
  ErrorMessage,
} from '../components/DesignPage.tsx'

export type ItemFormValues = {
  name: string
  type: ItemType
  author?: string
  artist?: string
  publisher?: string
  category: string
  brand: string
  color: string
  size: string
  material: string
  condition: ItemCondition
  roomId: number | null
  storageLocationId: number | null
  ownerProfileId?: number
  photos: string[]
  memory?: string
  notes?: string
}
interface ItemFormProps {
  initialValues?: ItemFormValues
  onSubmit: (values: ItemFormValues) => void
  onRetake?: () => void
  onClose: () => void
  initialStep?: 'details' | 'location'
  editing?: boolean
  busy?: boolean
}

export function ItemForm({
  initialValues,
  onSubmit,
  onClose,
  onRetake,
  initialStep = 'details',
  editing = false,
  busy = false,
}: ItemFormProps) {
  const auth = useAuth()
  const [places, setPlaces] = useState<Place[]>([])
  const [error, setError] = useState<string | null>(null)
  const [step, setStep] = useState(initialStep)
  const [placeId, setPlaceId] = useState('')
  const [precise, setPrecise] = useState('')
  const [values, setValues] = useState<ItemFormValues>({
    name: '',
    category: '',
    author: '',
    artist: '',
    publisher: '',
    brand: '',
    color: '',
    size: '',
    material: '',
    condition: 'bon',
    roomId: null,
    storageLocationId: null,
    ownerProfileId: auth.activeProfileId ?? undefined,
    photos: [],
    memory: '',
    notes: '',
    ...initialValues,
    type: initialValues?.type && isItemType(initialValues.type) ? initialValues.type : 'autre',
  })
  useEffect(() => {
    let live = true
    const load = () => {
      void listPlaces()
        .then((data) => {
          if (live) {
            setPlaces(data.places)
            setError(null)
          }
        })
        .catch((e) => {
          if (live) setError(errorText(e))
        })
    }
    load()
    window.addEventListener('focus', load)
    return () => {
      live = false
      window.removeEventListener('focus', load)
    }
  }, [])
  const roomPlace = places.find((p) => p.rooms.some((r) => r.id === values.roomId))
  const place = places.find((p) => String(p.id) === placeId) ?? roomPlace ?? places[0]
  const room = place?.rooms.find((r) => r.id === values.roomId)
  const roots = room?.storageLocations ?? []
  const root = roots.find(
    (s) =>
      s.id === values.storageLocationId ||
      flattenStorageLocations(s.children).some((c) => c.id === values.storageLocationId),
  )
  const children = root ? flattenStorageLocations(root.children) : []
  const patch = <K extends keyof ItemFormValues>(key: K, value: ItemFormValues[K]) =>
    setValues((v) => ({ ...v, [key]: value }))
  const save = (skip = false) => {
    if (!busy)
      onSubmit({
        ...values,
        brand: isBook(values.type) || isMusic(values.type) ? '' : values.brand,
        author: isBook(values.type) ? values.author : '',
        artist: isMusic(values.type) ? values.artist : '',
        publisher: isBook(values.type) || isMusic(values.type) ? values.publisher : '',
        size: isClothing(values.type) ? values.size : '',
        material: isClothing(values.type) ? values.material : '',
        ...(skip ? { roomId: null, storageLocationId: null } : {}),
      })
  }

  if (step === 'location')
    return (
      <DesignPage
        frame="160:264"
        household
        className="item-location-page"
        title="Lieu de rangement"
        subtitle="Vous pourrez le modifier plus tard."
        footer={
          <>
            <button
              className="btn btn-primary"
              type="submit"
              form="item-location-form"
              disabled={busy}
            >
              {busy ? 'Enregistrement…' : 'Enregistrer l’article'}
            </button>
            <button
              className="design-link"
              type="button"
              disabled={busy}
              onClick={() => save(true)}
            >
              Je le rangerai plus tard
            </button>
          </>
        }
      >
        <ErrorMessage error={error} />
        <DesignForm
          id="item-location-form"
          onSubmit={(e) => {
            e.preventDefault()
            save()
          }}
        >
          <DesignField label="Foyer">
            <select
              value={place?.id ?? ''}
              onChange={(e) => {
                setPlaceId(e.target.value)
                setValues((v) => ({ ...v, roomId: null, storageLocationId: null }))
                setPrecise('')
              }}
            >
              <option value="">Pas encore rangé</option>
              {places.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          </DesignField>
          <DesignField label="Pièce">
            <select
              value={values.roomId ?? ''}
              onChange={(e) => {
                setValues((v) => ({
                  ...v,
                  roomId: e.target.value ? Number(e.target.value) : null,
                  storageLocationId: null,
                }))
                setPrecise('')
              }}
            >
              <option value="">Pas encore rangé / inconnu</option>
              {place?.rooms.map((r) => (
                <option key={r.id} value={r.id}>
                  {r.name}
                </option>
              ))}
            </select>
          </DesignField>
          <DesignField label="Rangement">
            <select
              value={root?.id ?? ''}
              onChange={(e) => {
                patch('storageLocationId', e.target.value ? Number(e.target.value) : null)
                setPrecise('')
              }}
            >
              <option value="">Sans rangement précis</option>
              {roots.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </select>
          </DesignField>
          <DesignField label="Emplacement précis (optionnel)">
            <select
              value={
                precise ||
                (values.storageLocationId !== root?.id ? (values.storageLocationId ?? '') : '')
              }
              onChange={(e) => {
                setPrecise(e.target.value)
                patch(
                  'storageLocationId',
                  e.target.value ? Number(e.target.value) : (root?.id ?? null),
                )
              }}
            >
              <option value="">Aucun emplacement supplémentaire</option>
              {children.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.label}
                </option>
              ))}
            </select>
          </DesignField>
          <DesignNotice>
            <strong>
              <FigmaGlyph name="locationHeading" /> {place?.name ?? 'Lieu non défini'}{' '}
              {room ? `› ${room.name}` : ''}
            </strong>
            <p>
              {root?.name ?? 'Sans rangement'}{' '}
              {precise ? `› ${children.find((s) => String(s.id) === precise)?.label ?? ''}` : ''}
            </p>
          </DesignNotice>
          <div className="design-inline-links">
            <button type="button" onClick={() => setStep('details')}>
              Modifier l’article
            </button>
            <Link to="/foyer/rangement/ajouter" target="_blank" rel="noopener">
              Ajouter un rangement (nouvel onglet)
            </Link>
          </div>
        </DesignForm>
      </DesignPage>
    )

  return (
    <DesignPage
      frame="160:241"
      household
      className="item-details-form"
      title={editing ? 'Modifier l’article' : 'Nouvel article'}
      subtitle="Vérifiez les informations détectées."
      footer={
        <button className="btn btn-primary" type="submit" form="item-details-form">
          Choisir l’emplacement
        </button>
      }
    >
      <DesignForm
        id="item-details-form"
        onSubmit={(e) => {
          e.preventDefault()
          setStep('location')
        }}
      >
        <div className="item-photo-section">
          <label className="item-photo-picker">
            <span className="item-photo-picker__image">
              {values.photos[0] ? (
                <img src={values.photos[0]} alt="Article photographié" />
              ) : (
                <img className="item-photo-picker__symbol" src={itemSymbol} alt="" />
              )}
            </span>
            <strong>Photo de l’article</strong>
            <input
              type="file"
              accept="image/*"
              className="sr-only"
              aria-label="Ajouter une photo de l’article"
              onChange={(e) => {
                const f = e.target.files?.[0]
                e.target.value = ''
                if (f)
                  void resizeToBase64(f)
                    .then((photo) => patch('photos', [photo]))
                    .catch((e) => setError(errorText(e)))
              }}
            />
          </label>
          {values.photos.length > 0 && (
            <div className="scanner-review-controls">
              {onRetake && (
                <button type="button" className="design-link" onClick={onRetake}>
                  {itemText('Reprendre la photo', 'Retake photo')}
                </button>
              )}
              <button
                type="button"
                className="design-link"
                onClick={() => patch('photos', values.photos.slice(1))}
              >
                {itemText('Supprimer la photo', 'Remove photo')}
              </button>
            </div>
          )}
        </div>
        <DesignField label="Nom de l'article">
          <input
            id="item-name"
            required
            maxLength={255}
            value={values.name}
            onChange={(e) => patch('name', e.target.value)}
            placeholder="Ex. Pull noir"
          />
        </DesignField>
        <DesignField label="Type">
          <select
            id="item-type"
            value={values.type}
            onChange={(e) => patch('type', e.target.value as ItemFormValues['type'])}
          >
            {ITEM_TYPES.map((type) => (
              <option key={type} value={type}>
                {itemTypeLabel(type)}
              </option>
            ))}
          </select>
        </DesignField>
        {(isBook(values.type)
          ? ([
              ['author', itemText('Auteur / autrice', 'Author')],
              ['publisher', itemText('Maison d’édition', 'Publisher')],
            ] as const)
          : isMusic(values.type)
            ? ([
                ['artist', itemText('Artiste / groupe', 'Artist / band')],
                ['publisher', itemText('Label', 'Record label')],
              ] as const)
            : ([['brand', itemText('Marque', 'Brand')]] as const)
        ).map(([key, label]) => (
          <DesignField key={key} label={label}>
            <input
              value={values[key] ?? ''}
              maxLength={120}
              onChange={(e) => patch(key, e.target.value)}
            />
          </DesignField>
        ))}
        {isClothing(values.type) && (
          <>
            <DesignField label={itemText('Taille', 'Size')}>
              <input
                value={values.size}
                maxLength={30}
                onChange={(e) => patch('size', e.target.value)}
              />
            </DesignField>
            <DesignField label={itemText('Matière', 'Material')}>
              <input
                value={values.material}
                maxLength={120}
                onChange={(e) => patch('material', e.target.value)}
              />
            </DesignField>
          </>
        )}
        {!isBook(values.type) && !isMusic(values.type) && (
          <DesignField label="Couleur">
            <input
              value={values.color}
              onChange={(e) => patch('color', e.target.value)}
              maxLength={60}
            />
          </DesignField>
        )}
        <DesignField label={itemText('État', 'Condition')}>
          <select
            id="item-condition"
            value={values.condition}
            onChange={(e) => patch('condition', e.target.value as ItemCondition)}
          >
            {Object.entries({
              neuf: itemText('Neuf', 'New'),
              comme_neuf: itemText('Comme neuf', 'Like new'),
              tres_bon: itemText('Très bon état', 'Very good'),
              bon: itemText('Bon état', 'Good'),
              a_reparer: itemText('À réparer', 'Needs repair'),
              abime: itemText('Abîmé', 'Damaged'),
            }).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </DesignField>
        <details className="item-extra-fields">
          <summary>{itemText('Plus de détails (facultatif)', 'More details (optional)')}</summary>
          <DesignField label={itemText('Catégorie', 'Category')}>
            <input
              value={values.category}
              maxLength={120}
              onChange={(e) => patch('category', e.target.value)}
            />
          </DesignField>
          <DesignField label="Notes privées">
            <textarea
              value={values.notes}
              onChange={(e) => patch('notes', e.target.value)}
              maxLength={2000}
            />
          </DesignField>
          <DesignField label="Souvenir">
            <textarea
              value={values.memory}
              onChange={(e) => patch('memory', e.target.value)}
              maxLength={2000}
            />
          </DesignField>
        </details>
        <DesignField label="Propriétaire">
          <select
            disabled={editing || auth.activeProfile?.role === 'child'}
            value={values.ownerProfileId ?? ''}
            onChange={(e) => patch('ownerProfileId', Number(e.target.value))}
          >
            <option value="">Profil actif</option>
            {auth.profiles.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
        </DesignField>
        <ErrorMessage error={error} />
        <button className="design-link design-back" type="button" onClick={onClose}>
          Annuler
        </button>
      </DesignForm>
    </DesignPage>
  )
}
