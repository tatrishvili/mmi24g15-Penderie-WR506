import { Fragment } from 'react'
import { t } from 'i18next'
import OnboardingFoot from '../components/OnboardingFoot.tsx'
import bureau from '../images/icons/bureau_icon.svg'
import chambre from '../images/icons/chambre_icon.svg'
import cuisine from '../images/icons/Cusine_icon.svg'
import dressing from '../images/icons/dressing_icon.svg'
import garage from '../images/icons/garage_icon.svg'
import salon from '../images/icons/Salon_icon.svg'

const ROOM_ICONS = [dressing, chambre, bureau, salon, garage, cuisine]

export default function OnboardOrganise() {
  const organise = t('onboard.organise')

  const titleLine = organise?.titleLine
  const introLines = organise?.introLines
  const tabs = organise?.tabs
  const rooms = organise?.rooms
  const stepLabel = organise?.stepLabel

  return (
    <section className="welcome welcome--step">
      <div className="welcome__flow">
        <div className="organise">
          <h1 className="organise__title">{titleLine ?? 'Organise'}</h1>
          <p className="organise__intro">
            {introLines?.map((line, index) => (
              <Fragment key={line}>
                {index > 0 && ' '}
                <span>{line}</span>
              </Fragment>
            ))}
            {introLines == null && <span>{t('onboard.introDefault') ?? 'Intro'}&#x2026;</span>}
          </p>
          <ul className="organise__tabs">
            {tabs?.map((tab, index) => (
              <li key={tab} className={index === 0 ? 'organise__tab organise__tab--active' : 'organise__tab'}>
                {tab}
              </li>
            ))}
            {tabs == null && <li>{t('onboard.tabDefault') ?? 'Tab'}</li>}
          </ul>
          <ul className="organise__rooms">
            {rooms?.map((room, index) => (
              <li key={room} className="organise__room">
                <span className="organise__disc">
                  <img src={ROOM_ICONS[index]} alt="" />
                </span>
                <span className="organise__name">{room}</span>
              </li>
            ))}
            {rooms == null && <li>{t('onboard.roomDefault') ?? 'Room'}</li>}
          </ul>
        </div>
        <OnboardingFoot
          step={2}
          stepLabel={stepLabel ?? t('onboard.stepLabelDefault') ?? 'Étape 2'}
          nextLabel={t('onboard.next') ?? t('onboard.nextDefault') ?? 'Suivant'}
          nextTo="/onboarding/3"
          signInLabel={t('onboard.signIn') ?? t('onboard.signInDefault') ?? 'Connexion'}
        />
      </div>
    </section>
  )
}