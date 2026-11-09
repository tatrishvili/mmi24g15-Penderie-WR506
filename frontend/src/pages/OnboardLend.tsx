import { Fragment } from 'react'
import OnboardingFoot from '../components/OnboardingFoot.tsx'
import calendar from '../images/icons/calendar_borrow_icon.svg'
import demande from '../images/icons/demande_icon.svg'
import accept from '../images/icons/accept_icon.svg'
import pret from '../images/icons/pret_icon.svg'
import sophie from '../images/sophie-pfp.jpg'
import veste from '../images/Veste_beige.png'

const TIMELINE_ICONS = [demande, accept, pret]

export default function OnboardLend() {
  const { titleLines, itemName, status, since, borrower, chips, bodyLines, timeline, stepLabel } =
    t('onboard.lend')

  return (
    <section className="welcome welcome--step">
      <div className="welcome__flow">
        <div className="lend">
          <h1 className="lend__title">
            {titleLines.map((line, index) => (
              <Fragment key={line}>
                {index > 0 && ' '}
                <span>{line}</span>
              </Fragment>
            ))}
          </h1>

          <div className="lend__card step-card">
            <span className="lend__photo">
              <img src={veste} alt="" />
            </span>
            <div className="lend__info">
              <div className="lend__head">
                <span className="lend__name">{itemName}</span>
                <span className="lend__badge">{status}</span>
              </div>
              <p className="lend__since">
                <img src={calendar} alt="" />
                {since}
              </p>
              <div className="lend__person">
                <span className="lend__chevron" aria-hidden="true">
                  ›
                </span>
                <img className="lend__avatar" src={sophie} alt="" />
                <span className="lend__borrower">{borrower}</span>
              </div>
            </div>
            <ul className="lend__chips">
              {chips.map((chip) => (
                <li key={chip} className="lend__chip">
                  {chip}
                </li>
              ))}
            </ul>
          </div>

          <p className="lend__body">
            {bodyLines.map((line, index) => (
              <Fragment key={line}>
                {index > 0 && ' '}
                <span>{line}</span>
              </Fragment>
            ))}
          </p>

          <ol className="lend__timeline">
            {timeline.map((label, index) => (
              <li key={label} className="lend__step">
                <span className="lend__step-icon">
                  <img src={TIMELINE_ICONS[index]} alt="" />
                </span>
                <span className="lend__step-label">{label}</span>
              </li>
            ))}
          </ol>
        </div>
        <OnboardingFoot
          step={3}
          stepLabel={stepLabel}
          nextLabel={t('onboard.next')}
          nextTo="/onboarding/4"
          signInLabel={t('onboard.signIn')}
        />
      </div>
    </section>
  )
}
