import type { PreviewItem } from '../api/family.ts'
import { isItemType, itemText as t, itemTypeLabel } from '../lib/itemCatalog.ts'
function conditionText(value?: string) {
  return (
    {
      neuf: t('Neuf', 'New'),
      comme_neuf: t('Comme neuf', 'Like new'),
      tres_bon: t('Très bon état', 'Very good'),
      bon: t('Bon état', 'Good'),
      a_reparer: t('À réparer', 'Needs repair'),
      abime: t('Abîmé', 'Damaged'),
    }[value ?? ''] ?? ''
  )
}
export default function ChildSnapshotPreview({
  item,
  photoUrls,
}: {
  item: PreviewItem
  photoUrls?: string[]
}) {
  return (
    <div className="family-preview">
      <strong>{item.name}</strong>
      <div className="family-preview-photos">
        {(photoUrls ?? item.photos.map((photo) => photo.url)).map((url, index) => (
          <img key={index} src={url} alt={`${item.name} · ${index + 1}`} />
        ))}
      </div>
      <p>
        {[
          item.type && isItemType(item.type) ? itemTypeLabel(item.type) : item.type,
          item.color,
          item.brand,
          item.author,
          item.artist,
          item.publisher,
          conditionText(item.condition),
        ]
          .filter(Boolean)
          .join(' · ')}
      </p>
    </div>
  )
}
