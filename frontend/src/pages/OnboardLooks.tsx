import { Fragment } from 'react'
import OnboardingFoot from '../components/OnboardingFoot.tsx'
import { t, tArray } from '../i18n/copy.ts'
import dislike from '../images/icons/dislike.svg'
import like from '../images/icons/like.svg'
import sun from '../images/icons/sun.svg'
import outfit from '../images/outfit_of_the_day_example.png'

export default function OnboardLooks() {
  const titleLines = tArray<string>('onboard.look.titleLines')
  const bodyLines = tArray<string>('onboard.look.bodyLines')
  const temperature = t('onboard.look.temperature')
  const today = t('onboard.look.today')
  const likeLabel = t('onboard.look.like')
  const skip = t('onboard.look.skip')
  const caption = t('onboard.look.caption')
  const stepLabel = t('onboard.look.stepLabel')

  return (
    <section className="welcome welcome--step">
      <div className="welcome__flow">
        <div className="look">
          <h1 className="look__title">
            {titleLines.map((line, index) => (
              <Fragment key={line}>
                {index > 0 && ' '}
                <span>{line}</span>
              </Fragment>
            ))}
          </h1>
          <p className="look__body">
            {bodyLines.map((line, index) => (
              <Fragment key={line}>
                {index > 0 && ' '}
                <span>{line}</span>
              </Fragment>
            ))}
          </p>

          <div className="look__card step-card">
            <div className="look__row">
              <span className="look__outfit">
                <img src={outfit} alt="" />
              </span>
              <div className="look__weather">
                <span className="look__weather-top">
                  <img src={sun} alt="" />
                  <span className="look__temp">{temperature}</span>
                </span>
                <span className="look__today">{today}</span>
              </div>
            </div>
            <div className="look__actions">
              <span className="look__choice">
                <img src={like} alt="" />
                {likeLabel}
              </span>
              <span className="look__choice">
                <img src={dislike} alt="" />
                {skip}
              </span>
            </div>
          </div>

          <p className="look__caption">{caption}</p>
        </div>
        <OnboardingFoot
          step={4}
          stepLabel={stepLabel}
          nextLabel={t('onboard.next')}
          nextTo="/inscription"
          signInLabel={t('onboard.signIn')}
        />
      </div>
    </section>
  )
}
