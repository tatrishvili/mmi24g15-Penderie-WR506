import { Fragment } from 'react'
import OnboardingFoot from '../components/OnboardingFoot.tsx'
import { t, tArray } from '../i18n/copy.ts'
import bureau from '../images/icons/bureau_icon.svg'
import chambre from '../images/icons/chambre_icon.svg'
import cuisine from '../images/icons/Cusine_icon.svg'
import dressing from '../images/icons/dressing_icon.svg'
import garage from '../images/icons/garage_icon.svg'
import salon from '../images/icons/Salon_icon.svg'

const ROOM_ICONS = [dressing, chambre, bureau, salon, garage, cuisine]

export default function OnboardOrganise() {
  const titleLine = t('onboard.organise.titleLine')
  const introLines = tArray<string>('onboard.organise.introLines')
  const tabs = tArray<string>('onboard.organise.tabs')
  const rooms = tArray<string>('onboard.organise.rooms')
  const stepLabel = t('onboard.organise.stepLabel')

  return (
    <section className="welcome welcome--step">
      <div className="welcome__flow">
        <div className="organise">
          <h1 className="organise__title">{titleLine}</h1>
          <p className="organise__intro">
            {introLines.map((line, index) => (
              <Fragment key={line}>
                {index > 0 && ' '}
                <span>{line}</span>
              </Fragment>
            ))}
          </p>
          <ul className="organise__tabs">
            {tabs.map((tab, index) => (
              <li key={tab} className={index === 0 ? 'organise__tab organise__tab--active' : 'organise__tab'}>
                {tab}
              </li>
            ))}
          </ul>
          <ul className="organise__rooms">
            {rooms.map((room, index) => (
              <li key={room} className="organise__room">
                <span className="organise__disc">
                  <img src={ROOM_ICONS[index]} alt="" />
                </span>
                <span className="organise__name">{room}</span>
              </li>
            ))}
          </ul>
        </div>
        <OnboardingFoot
          step={2}
          stepLabel={stepLabel}
          nextLabel={t('onboard.next')}
          nextTo="/onboarding/3"
          signInLabel={t('onboard.signIn')}
        />
      </div>
    </section>
  )
}