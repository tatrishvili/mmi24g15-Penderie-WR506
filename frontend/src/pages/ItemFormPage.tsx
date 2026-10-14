import { useEffect, useRef, useState } from 'react'
import { resizeToBase64 } from '../lib/image.ts'
import { listPlaces } from '../api/places.ts'
import type { Place } from '../types/location.ts'
import { flattenStorageLocations } from '../types/location.ts'
import { ApiError } from '../api/client.ts'

type ItemFormValues = {
  name: string
  type: 'pull' | 'tshirt' | 'pantalon' | 'jupe' | 'robe' | 'chaussure' | 'sac' | 'autre'
  category: string
  brand: string
  color: string
  size: string
  material: string
  condition: 'neuf' | 'comme_neuf' | 'tres_bon' | 'bon' | 'a_reparer' | 'abime'
  roomId: number | null
  storageLocationId: number | null
  photos: string[]  // base64 data URLs
  memory?: string
  notes?: string
}

interface ItemFormProps {
  initialValues?: ItemFormValues
  onSubmit: (values: ItemFormValues) => void
  onClose: () => void
}

export function ItemForm({ initialValues, onSubmit, onClose }: ItemFormProps) {
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [places, setPlaces] = useState<Place[]>([])
  const [placeError, setPlaceError] = useState<string | null>(null)
  const [values, setValues] = useState<ItemFormValues>({
    name: initialValues?.name ?? '',
    type: initialValues?.type ?? 'autre',
    category: initialValues?.category ?? '',
    brand: initialValues?.brand ?? '',
    color: initialValues?.color ?? '',
    size: initialValues?.size ?? '',
    material: initialValues?.material ?? '',
    condition: initialValues?.condition ?? 'bon',
    roomId: initialValues?.roomId ?? null,
    storageLocationId: initialValues?.storageLocationId ?? null,
    photos: initialValues?.photos ?? [],
    memory: initialValues?.memory ?? '',
    notes: initialValues?.notes ?? '',
  })

  useEffect(() => {
    let cancelled = false
    listPlaces()
      .then(({ places: loadedPlaces }) => {
        if (!cancelled) setPlaces(loadedPlaces)
      })
      .catch((error: unknown) => {
        if (!cancelled) {
          setPlaceError(error instanceof ApiError ? error.message : 'Impossible de charger les lieux.')
        }
      })
    return () => {
      cancelled = true
    }
  }, [])

  const rooms = places.flatMap((place) =>
    place.rooms.map((room) => ({ ...room, placeName: place.name })),
  )
  const selectedRoom = rooms.find((room) => room.id === values.roomId)
  const storageOptions = selectedRoom === undefined
    ? []
    : flattenStorageLocations(selectedRoom.storageLocations)

  const onChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>,
  ) => {
    const { name, value } = e.target
    setValues({ ...values, [name]: value as any })
  }

  return (
    <div className="max-w-md p-4">
      <button
        className="absolute top-2 right-2 text-gray-500 hover:text-rose-500"
        onClick={onClose}
      >
        &times;
      </button>

      <h2 className="mt-6 text-xl font-bold text-rose-500 mb-4">Nouvel article</h2>

      <form
        className="space-y-4"
        onSubmit={(e) => {
          e.preventDefault()
          onSubmit(values)
        }}
      >
        <div>
          <label htmlFor="item-name" className="block text-sm font-medium mb-1">Nom de l'article</label>
          <input
            type="text"
            id="item-name"
            name="name"
            value={values.name}
            onChange={onChange}
            required
            className="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-rose-300"
          />
        </div>

        <div>
          <label htmlFor="item-type" className="block text-sm font-medium mb-1">Type</label>
          <select id="item-type"
            name="type" value={values.type} onChange={onChange} className="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-rose-300">
            <option value="pull">Pull</option>
            <option value="tshirt">T-shirt</option>
            <option value="pantalon">Pantalon</option>
            <option value="jupe">Jupe</option>
            <option value="robe">Robe</option>
            <option value="chaussure">Chaussure</option>
            <option value="sac">Sac</option>
            <option value="autre">Autre</option>
          </select>
        </div>

        <div>
          <label htmlFor="item-category" className="block text-sm font-medium mb-1">Catégorie</label>
          <input
            type="text"
            id="item-category"
            name="category"
            value={values.category}
            onChange={onChange}
            className="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-rose-300"
          />
        </div>

        <div>
          <label htmlFor="item-brand" className="block text-sm font-medium mb-1">Marque</label>
          <input
            type="text"
            id="item-brand"
            name="brand"
            value={values.brand}
            onChange={onChange}
            className="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-rose-300"
          />
        </div>

        <div>
          <label htmlFor="item-color" className="block text-sm font-medium mb-1">Couleur</label>
          <input
            type="text"
            id="item-color"
            name="color"
            value={values.color}
            onChange={onChange}
            className="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-rose-300"
          />
        </div>

        <div>
          <label htmlFor="item-size" className="block text-sm font-medium mb-1">Taille</label>
          <input
            type="text"
            id="item-size"
            name="size"
            value={values.size}
            onChange={onChange}
            className="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-rose-300"
          />
        </div>

        <div>
          <label htmlFor="item-material" className="block text-sm font-medium mb-1">Matériau</label>
          <input
            type="text"
            id="item-material"
            name="material"
            value={values.material}
            onChange={onChange}
            className="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-rose-300"
          />
        </div>

        <div>
          <label htmlFor="item-condition" className="block text-sm font-medium mb-1">État</label>
          <select
            id="item-condition"
            name="condition"
            value={values.condition}
            onChange={onChange}
            className="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-rose-300"
          >
            <option value="neuf">Neuf</option>
            <option value="comme_neuf">Comme neuf</option>
            <option value="tres_bon">Très bon</option>
            <option value="bon">Bon</option>
            <option value="a_reparer">À réparer</option>
            <option value="abime">Abîmé</option>
          </select>
        </div>

        <fieldset className="item-location">
          <legend>Emplacement (optionnel)</legend>
          <div className="field">
            <label htmlFor="item-room">Maison et pièce</label>
            <select
              id="item-room"
              className="input"
              value={values.roomId ?? ''}
              onChange={(event) => {
                const roomId = event.target.value === '' ? null : Number(event.target.value)
                setValues({ ...values, roomId, storageLocationId: null })
              }}
            >
              <option value="">Pas encore rangé / inconnu</option>
              {rooms.map((room) => (
                <option key={room.id} value={room.id}>{room.placeName} › {room.name}</option>
              ))}
            </select>
          </div>
          {values.roomId !== null && (
            <div className="field">
              <label htmlFor="item-storage">Rangement, étagère ou boîte</label>
              <select
                id="item-storage"
                className="input"
                value={values.storageLocationId ?? ''}
                onChange={(event) => {
                  const storageLocationId = event.target.value === '' ? null : Number(event.target.value)
                  setValues({ ...values, storageLocationId })
                }}
              >
                <option value="">Dans la pièce, sans rangement précis</option>
                {storageOptions.map((option) => (
                  <option key={option.id} value={option.id}>{option.label}</option>
                ))}
              </select>
              {storageOptions.length === 0 && (
                <span className="field-hint">Ajoutez des rangements dans l’onglet Lieux.</span>
              )}
            </div>
          )}
          {placeError !== null && <p role="alert" className="form-error">{placeError}</p>}
        </fieldset>

        <div>
          <label className="block text-sm font-medium mb-1">Photos</label>
          <div className="grid grid-cols-2 gap-2">
            {values.photos.map((photo, index) => (
              <img
                key={index}
                src={photo}
                alt="Photo de l'article"
                className="w-full h-32 object-cover rounded"
              />
            ))}
            {values.photos.length < 3 && (
              <>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/*"
                  name="photos"
                  className="hidden"
                  onChange={(e) => {
                    const file = e.target.files?.[0]
                    if (!file) return
                    resizeToBase64(file).then((base64) =>
                      setValues({ ...values, photos: [...values.photos, base64] }),
                    )
                  }}
                />
                <button
                  type="button"
                  className="px-3 py-2 border border-dashed border-rose-500 rounded hover:bg-rose-50 text-sm"
                  onClick={() => fileInputRef.current?.click()}
                >
                  Ajouter photo
                </button>
              </>
            )}
          </div>
        </div>

        <div>
          <label htmlFor="item-memory" className="block text-sm font-medium mb-1">Souvenir (optionnel)</label>
          <textarea
            id="item-memory"
            name="memory"
            value={values.memory}
            onChange={onChange}
            rows={2}
            className="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-rose-300"
          />
        </div>

        <div>
          <label htmlFor="item-notes" className="block text-sm font-medium mb-1">Notes (optionnel)</label>
          <textarea
            id="item-notes"
            name="notes"
            value={values.notes}
            onChange={onChange}
            rows={2}
            className="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-rose-300"
          />
        </div>

        <div className="mt-6">
          <button
            type="submit"
            className="w-full px-4 py-2 bg-rose-500 text-white rounded hover:bg-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-300"
          >
            Enregistrer
          </button>
        </div>
      </form>
    </div>
  )
}