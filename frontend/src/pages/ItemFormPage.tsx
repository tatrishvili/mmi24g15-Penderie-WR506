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
  type: 'pull' | 'tshirt' | 'pantalon' | 'jupe' | 'robe' | 'chaussure' | 'sac' | 'autre'
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
  onClose: () => void
  initialStep?: 'details' | 'location'
  editing?: boolean
  busy?: boolean
}

export function ItemForm({
  initialValues,
  onSubmit,
  onClose,
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
    type: 'autre',
    category: '',
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
    if (!busy) onSubmit({ ...values, ...(skip ? { roomId: null, storageLocationId: null } : {}) })
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
                if (f)
                  void resizeToBase64(f)
                    .then((photo) => patch('photos', [photo]))
                    .catch((e) => setError(errorText(e)))
              }}
            />
          </label>
          <details className="item-extra-fields">
            <summary>Plus de détails (facultatif)</summary>
            {(
              [
                ['category', 'Catégorie'],
                ['brand', 'Marque'],
                ['size', 'Taille'],
                ['material', 'Matière'],
              ] as const
            ).map(([key, label]) => (
              <DesignField key={key} label={label}>
                <input
                  value={values[key]}
                  onChange={(e) => patch(key, e.target.value)}
                  maxLength={key === 'size' ? 30 : 120}
                />
              </DesignField>
            ))}
            <DesignField label="État">
              <select
                id="item-condition"
                value={values.condition}
                onChange={(e) => patch('condition', e.target.value as ItemCondition)}
              >
                {Object.entries({
                  neuf: 'Neuf',
                  comme_neuf: 'Comme neuf',
                  tres_bon: 'Très bon état',
                  bon: 'Bon état',
                  a_reparer: 'À réparer',
                  abime: 'Abîmé',
                }).map(([v, l]) => (
                  <option key={v} value={v}>
                    {l}
                  </option>
                ))}
              </select>
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
            {Object.entries({
              pull: 'Pull',
              tshirt: 'T-shirt',
              pantalon: 'Pantalon',
              jupe: 'Jupe',
              robe: 'Robe',
              chaussure: 'Chaussure',
              sac: 'Sac',
              autre: 'Autre',
            }).map(([v, l]) => (
              <option key={v} value={v}>
                {l}
              </option>
            ))}
          </select>
        </DesignField>
        <DesignField label="Couleur">
          <input
            value={values.color}
            onChange={(e) => patch('color', e.target.value)}
            placeholder="Couleur (facultatif)"
            maxLength={60}
          />
        </DesignField>
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
