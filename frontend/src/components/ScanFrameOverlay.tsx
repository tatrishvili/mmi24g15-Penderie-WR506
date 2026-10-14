import type { GarmentType } from './garmentGuide.ts'

const GUIDANCE: Record<GarmentType, string> = {
  tshirt: 'Placez le T-shirt entier dans le cadre',
  pantalon: 'Placez le pantalon entier dans le cadre',
  jupe: 'Placez la jupe entière dans le cadre',
  autre: 'Placez un seul objet entier dans le cadre',
}

export function ScanFrameOverlay({ type }: { type: GarmentType }) {
  return (
    <div className="scan-guidance" aria-label={GUIDANCE[type]}>
      <div className={`scan-guide-shape scan-guide-shape--${type}`} aria-hidden="true" />
      <span className="scan-guidance-label">{GUIDANCE[type]}</span>
    </div>
  )
}
