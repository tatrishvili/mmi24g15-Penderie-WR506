import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { ReactNode } from 'react'
import OnboardingPage from './OnboardingPage.tsx'

function renderStep(step: string) {
  const page: ReactNode = (
    <MemoryRouter initialEntries={[`/onboarding/${step}`]}>
      <Routes>
        <Route path="/" element={<h1>Accueil</h1>} />
        <Route path="/onboarding/:step" element={<OnboardingPage />} />
      </Routes>
    </MemoryRouter>
  )

  return render(page)
}

describe('Onboarding steps', () => {
  afterEach(() => {
    cleanup()
  })

  it('shows the rooms step and links to the lending step', () => {
    renderStep('2')

    expect(screen.getByRole('heading', { name: 'Tout à sa place' })).toBeInTheDocument()
    expect(screen.getByText('Pièces')).toBeInTheDocument()
    expect(screen.getByText('Cuisine')).toBeInTheDocument()
    expect(screen.getByRole('img', { name: 'Étape 2 sur 4' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Suivant' })).toHaveAttribute('href', '/onboarding/3')
    expect(screen.getByRole('link', { name: 'Se connecter' })).toHaveAttribute('href', '/connexion')
  })

  it('shows the lending step and links to the looks step', () => {
    renderStep('3')

    expect(
      screen.getByRole('heading', { name: 'Prêtez vos affaires sans jamais oublier' }),
    ).toBeInTheDocument()
    expect(screen.getByText('veste beige')).toBeInTheDocument()
    expect(screen.getByText('Prêtée')).toBeInTheDocument()
    expect(screen.getByText('Sophie')).toBeInTheDocument()
    expect(screen.getByText('En prêt')).toBeInTheDocument()
    expect(screen.getByRole('img', { name: 'Étape 3 sur 4' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Suivant' })).toHaveAttribute('href', '/onboarding/4')
  })

  it('shows the looks step and links to registration', () => {
    renderStep('4')

    expect(screen.getByRole('heading', { name: 'Des looks chaque jour' })).toBeInTheDocument()
    expect(screen.getByText('18°')).toBeInTheDocument()
    expect(screen.getByText('J’aime')).toBeInTheDocument()
    expect(screen.getByText('Pas pour moi')).toBeInTheDocument()
    expect(screen.getByRole('img', { name: 'Étape 4 sur 4' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Suivant' })).toHaveAttribute('href', '/inscription')
  })

  it('falls back to the welcome screen for an unknown step', () => {
    renderStep('99')

    expect(screen.getByRole('heading', { name: 'Accueil' })).toBeInTheDocument()
  })
})
