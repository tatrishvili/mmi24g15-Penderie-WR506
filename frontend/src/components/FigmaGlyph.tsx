import roomSearch from '../images/figma/room-search-text.svg'
import homeSearch from '../images/figma/home-search-text.svg'
import roomCheck from '../images/figma/room-check.svg'
import scanButton from '../images/figma/scan-button-text.svg'
import shareCheck from '../images/figma/share-check-text.svg'
import locationHeading from '../images/figma/location-heading-text.svg'

const sources = { roomSearch, homeSearch, roomCheck, scanButton, shareCheck, locationHeading }

export default function FigmaGlyph({ name }: { name: keyof typeof sources }) {
  return (
    <span className={`figma-glyph figma-glyph--${name}`} aria-hidden="true">
      <img src={sources[name]} alt="" data-figma-glyph={name} />
    </span>
  )
}
