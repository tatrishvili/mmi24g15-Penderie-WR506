import type { GarmentType } from './garmentGuide.ts'
import { itemText } from '../lib/itemCatalog.ts'

const SHAPES: Record<GarmentType, string> = {
  tshirt:
    'M90 55 L55 68 L22 115 L61 142 L78 119 L78 251 L222 251 L222 119 L239 142 L278 115 L245 68 L210 55 Q200 82 150 82 Q100 82 90 55 Z',
  pantalon: 'M86 30 L214 30 L235 272 L168 272 L150 122 L132 272 L65 272 Z',
  jupe: 'M106 55 L194 55 L255 252 Q150 277 45 252 Z',
  autre: 'M70 48 H230 Q246 48 246 64 V236 Q246 252 230 252 H70 Q54 252 54 236 V64 Q54 48 70 48 Z',
}
export function ScanFrameOverlay({ type }: { type: GarmentType }) {
  const guidance = itemText(
    {
      tshirt: 'Placez le T-shirt entier dans le cadre',
      pantalon: 'Placez le pantalon entier dans le cadre',
      jupe: 'Placez la jupe entière dans le cadre',
      autre: 'Placez un seul objet entier dans le cadre',
    }[type],
    {
      tshirt: 'Fit the whole T-shirt inside the outline',
      pantalon: 'Fit the whole pair of trousers inside the outline',
      jupe: 'Fit the whole skirt inside the outline',
      autre: 'Fit one whole object inside the outline',
    }[type],
  )
  return (
    <div className="scan-guidance" aria-label={guidance}>
      <svg
        className="scan-guide-outline"
        viewBox="0 0 300 300"
        aria-hidden="true"
        data-guide={type}
      >
        <path d={SHAPES[type]} />
      </svg>
      <span className="scan-guidance-label">{guidance}</span>
    </div>
  )
}
