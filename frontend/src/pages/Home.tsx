import { Fragment } from 'react'
import { Link } from 'react-router-dom'
import { getCopy } from '../i18n/copy.ts'
import heroPhoto from '../images/penderie-home-page.jpg'
import logo from '../images/penderie-logo.png'

const CURVE_VIEWBOX = '0 0 402 874'

const CURVE_PATH =
  'M0,430C3.3,427.5 13.3,419.8 20,415C26.7,410.2 33.3,405 40,401C46.7,397 53.3,393.8 60,391C66.7,388.2 73.3,386.2 80,384C86.7,381.8 93.3,379.7 100,378C106.7,376.3 113.3,375.2 120,374C126.7,372.8 133.3,371.7 140,371C146.7,370.3 153.3,370.3 160,370C166.7,369.7 173.3,369.8 180,369C186.7,368.2 193.3,366.5 200,365C206.7,363.5 213.3,362.2 220,360C226.7,357.8 233.3,354.8 240,352C246.7,349.2 253.3,346.3 260,343C266.7,339.7 273.3,336 280,332C286.7,328 293.3,323.5 300,319C306.7,314.5 313.3,310 320,305C326.7,300 333.3,294.5 340,289C346.7,283.5 353.3,278.2 360,272C366.7,265.8 373.3,258.9 380,252C386.7,245.1 396.5,234.2 402,230L402,874L0,874Z'

export default function Home() {
  const copy = getCopy().home

  return (
    <section className="welcome">
      <img src={heroPhoto} alt="" className="welcome__photo" />
      <svg
        className="welcome__curve"
        viewBox={CURVE_VIEWBOX}
        preserveAspectRatio="none"
        aria-hidden="true"
        focusable="false"
      >
        <path d={CURVE_PATH} />
      </svg>
      <img src={logo} alt="Penderie" className="welcome__logo" />

      <div className="welcome__body">
        <h1 className="welcome__title">
          {copy.titleLines.map((line, index) => (
            <Fragment key={line}>
              {index > 0 && ' '}
              <span>{line}</span>
            </Fragment>
          ))}
        </h1>
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
