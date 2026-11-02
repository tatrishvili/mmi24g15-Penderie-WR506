import { errorText } from '../lib/errors.ts'
import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { createItem, getItem, updateItem } from '../api/items.ts'
import { api } from '../api/client.ts'
import { ItemForm } from './ItemFormPage.tsx'
import type { ItemFormValues } from './ItemFormPage.tsx'
import type { ItemPhoto } from '../types/item.ts'
import { ErrorMessage } from '../components/DesignPage.tsx'

export default function ItemEditorPage({ locationOnly = false }: { locationOnly?: boolean }) {
  const { id } = useParams()
  const navigate = useNavigate()
  const [values, setValues] = useState<ItemFormValues>()
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [originalPhotos, setOriginalPhotos] = useState<ItemPhoto[]>([])
  useEffect(() => {
    if (!id) return
    let live = true
    getItem(Number(id))
      .then(({ item }) => {
        if (live) {
          setOriginalPhotos(item.photos)
          setValues({
            name: item.name,
            type: (item.type ?? 'autre') as ItemFormValues['type'],
            category: item.category ?? '',
            brand: item.brand ?? '',
            author: item.author ?? '',
            artist: item.artist ?? '',
            publisher: item.publisher ?? '',
            color: item.color ?? '',
            size: item.size ?? '',
            material: item.material ?? '',
            condition: item.condition,
            roomId: item.room?.id ?? null,
            storageLocationId: item.storageLocation?.id ?? null,
            photos: item.photos.map((p) => p.url),
            ownerProfileId: item.ownerProfile.id,
            memory: item.memory ?? '',
            notes: item.notes ?? '',
          })
        }
      })
      .catch((e) => {
        if (live) setError(errorText(e))
      })
    return () => {
      live = false
    }
  }, [id])
  async function save(data: ItemFormValues) {
    if (busy) return
    setBusy(true)
    setError(null)
    try {
      const payload = { ...data, photos: data.photos.filter((p) => p.startsWith('data:')) }
      if (id) {
        await updateItem(Number(id), payload)
        if (payload.photos.length)
          await api(`/api/items/${id}/photos`, { method: 'POST', body: { photos: payload.photos } })
        for (const photo of originalPhotos.filter((photo) => !data.photos.includes(photo.url))) {
          await api(`/api/items/${id}/photos/${photo.id}`, { method: 'DELETE' })
        }
        navigate(`/articles/${id}`)
      } else {
        const { item } = await createItem(payload)
        navigate(`/articles/${item.id}`)
      }
    } catch (e) {
      setError(errorText(e))
    } finally {
      setBusy(false)
    }
  }
  return (
    <>
      <ErrorMessage error={error} />
      {id && !values ? (
        <p role="status">{error ? 'Article indisponible.' : 'Chargement…'}</p>
      ) : (
        <ItemForm
          key={id ?? 'new'}
          initialValues={values}
          editing={!!id}
          initialStep={locationOnly ? 'location' : 'details'}
          busy={busy}
          onSubmit={(data) => void save(data)}
          onClose={() => navigate(id ? `/articles/${id}` : '/dressing')}
        />
      )}
    </>
  )
}
