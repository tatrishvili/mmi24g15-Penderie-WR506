import { Fragment } from 'react'
import { Link } from 'react-router-dom'
import { getCopy } from '../i18n/copy.ts'
import buddie from '../images/icons/home_page_buddie.svg'

const BG_VIEWBOX = '0 0 402 874'

const WASH_PATH =
  'M0,0 L402,0 L402,112 C399,112.7 395.8,114.7 394,116 C392.2,117.3 390.8,118.7 389,120 C387.2,121.3 385,122.7 383,124 C381,125.3 379.2,126.7 377,128 C374.8,129.3 372.2,130.7 370,132 C367.8,133.3 366,134.7 364,136 C362,137.3 360.2,138.7 358,140 C355.8,141.3 353.3,142.7 351,144 C348.7,145.3 346.3,146.7 344,148 C341.7,149.3 339.3,150.7 337,152 C334.7,153.3 332.5,154.7 330,156 C327.5,157.3 324.5,158.7 322,160 C319.5,161.3 317.5,162.7 315,164 C312.5,165.3 309.8,166.7 307,168 C304.2,169.3 301,170.7 298,172 C295,173.3 292,174.7 289,176 C286,177.3 283.2,178.7 280,180 C276.8,181.3 273.5,182.7 270,184 C266.5,185.3 262.8,186.7 259,188 C255.2,189.3 251.5,190.7 247,192 C242.5,193.3 237.5,194.7 232,196 C226.5,197.3 224.2,198.7 214,200 C203.8,201.3 184.8,202.7 171,204 C157.2,205.3 141.5,206.7 131,208 C120.5,209.3 114.5,210.7 108,212 C101.5,213.3 97,214.7 92,216 C87,217.3 82.2,218.7 78,220 C73.8,221.3 70.7,222.7 67,224 C63.3,225.3 59.3,226.7 56,228 C52.7,229.3 49.8,230.7 47,232 C44.2,233.3 41.5,234.7 39,236 C36.5,237.3 38.5,238.3 32,240 C25.5,241.7 5.3,245 0,246 Z'

const BOTTOM_PATH =
  'M0,581 C7.5,585.3 12.3,589.3 15,592 C17.7,594.7 20,597.3 22,600 C24,602.7 25.7,605.3 27,608 C28.3,610.7 29,613.3 30,616 C31,618.7 31.8,620.7 33,624 C34.2,627.3 35.8,632 37,636 C38.2,640 39.2,644.7 40,648 C40.8,651.3 41.5,653.3 42,656 C42.5,658.7 42.7,661.3 43,664 C43.3,666.7 43.7,669.3 44,672 C44.3,674.7 44.7,677.3 45,680 C45.3,682.7 45,685.3 46,688 C47,690.7 49.2,693.3 51,696 C52.8,698.7 54.5,700.3 57,704 C59.5,707.7 61.8,713 66,718 C70.2,723 74.3,728.7 82,734 C89.7,739.3 100.3,745.8 112,750 C123.7,754.2 137.3,756.8 152,759 C166.7,761.2 183.7,762.7 200,763 C216.3,763.3 234.7,762.7 250,761 C265.3,759.3 280.3,755.3 292,753 C303.7,750.7 312.5,748.2 320,747 C327.5,745.8 333,745.8 337,746 C341,746.2 342.5,750.7 344,748 C345.5,745.3 345,735.3 346,730 C347,724.7 348.5,720 350,716 C351.5,712 353.2,709 355,706 C356.8,703 358.5,700.7 361,698 C363.5,695.3 366.5,692.7 370,690 C373.5,687.3 378.7,683.8 382,682 C385.3,680.2 386.7,680 390,679 C393.3,678 400,676.5 402,676 L402,874 L0,874 Z'

export default function Home() {
  const copy = getCopy().home

  return (
    <section className="welcome">
      <svg
        className="welcome__bg"
        viewBox={BG_VIEWBOX}
        preserveAspectRatio="none"
        aria-hidden="true"
        focusable="false"
      >
        <defs>
          <linearGradient id="welcome-wash" x1="0" y1="0" x2="0" y2="260" gradientUnits="userSpaceOnUse">
            <stop offset="0" style={{ stopColor: 'var(--rose-wash)' }} />
            <stop offset="1" style={{ stopColor: 'var(--creme)' }} />
          </linearGradient>
          <linearGradient id="welcome-blob" x1="0" y1="686" x2="0" y2="862" gradientUnits="userSpaceOnUse">
            <stop offset="0" stopOpacity="1" style={{ stopColor: 'var(--rose-wash)' }} />
            <stop offset="1" stopOpacity="0" style={{ stopColor: 'var(--rose-wash)' }} />
          </linearGradient>
        </defs>
        <path d={WASH_PATH} fill="url(#welcome-wash)" />
        <path d={BOTTOM_PATH} fill="url(#welcome-blob)" />
      </svg>

      <div className="welcome__body">
        <div className="welcome__head">
          <img src={buddie} alt="" className="welcome__buddie" />
          <h1 className="welcome__title">
            {copy.titleLines.map((line, index) => (
              <Fragment key={line}>
                {index > 0 && ' '}
                <span>{line}</span>
              </Fragment>
            ))}
          </h1>
        </div>
        <p className="welcome__tagline">{copy.tagline}</p>

        <div className="welcome__foot">
          <div className="welcome__dots" role="img" aria-label={copy.stepLabel}>
            <span className="dot dot--active" />
            <span className="dot" />
            <span className="dot" />
          </div>
          <Link to="/inscription" className="btn btn-primary welcome__next">
            {copy.next}
          </Link>
          <Link to="/connexion" className="welcome__signin">
            {copy.signIn}
          </Link>
        </div>
      </div>
    </section>
  )
}
