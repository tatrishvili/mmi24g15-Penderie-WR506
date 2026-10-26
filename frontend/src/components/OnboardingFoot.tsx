import { Link } from 'react-router-dom'
import StepDots from './StepDots.tsx'

type OnboardingFootProps = {
  step: number
  stepLabel: string
  nextLabel: string
  nextTo: string
  signInLabel: string
}

export default function OnboardingFoot({
  step,
  stepLabel,
  nextLabel,
  nextTo,
  signInLabel,
}: OnboardingFootProps) {
  return (
    <div className="welcome__foot">
      <StepDots step={step} label={stepLabel} />
      <Link to={nextTo} className="btn btn-primary welcome__next">
        {nextLabel}
      </Link>
      <Link to="/connexion" className="welcome__signin">
        {signInLabel}
      </Link>
    </div>
  )
}
