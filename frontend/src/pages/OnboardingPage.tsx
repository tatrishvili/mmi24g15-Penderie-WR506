import type { ComponentType } from 'react'
import { Navigate, useParams } from 'react-router-dom'
import OnboardLend from './OnboardLend.tsx'
import OnboardLooks from './OnboardLooks.tsx'
import OnboardOrganise from './OnboardOrganise.tsx'

const STEPS: Record<string, ComponentType> = {
  '2': OnboardOrganise,
  '3': OnboardLend,
  '4': OnboardLooks,
}

export default function OnboardingPage() {
  const { step } = useParams()
  const Step = step ? STEPS[step] : undefined

  if (!Step) return <Navigate to="/" replace />

  return <Step />
}
