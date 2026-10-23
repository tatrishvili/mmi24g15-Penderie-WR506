import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import type { ReactNode } from 'react'
import Home from './Home.tsx'

function renderHome() {
  const page: ReactNode = (
    <MemoryRouter>
      <Home />
    </MemoryRouter>
  )

  render(page)
}

describe('Home', () => {
  afterEach(() => {
    cleanup()
  })

  it('renders the welcome onboarding screen', () => {
    renderHome()

    expect(screen.getByRole('heading', { name: 'Bienvenue chez Penderie' })).toBeInTheDocument()
    expect(screen.getByText(/Ajoutez tous vos vêtements/)).toBeInTheDocument()
    expect(screen.getByAltText('Penderie')).toBeInTheDocument()
  })

  it('links to registration and sign-in', () => {
    renderHome()

    expect(screen.getByRole('link', { name: 'Suivant' })).toHaveAttribute('href', '/inscription')
    expect(screen.getByRole('link', { name: 'Se connecter' })).toHaveAttribute('href', '/connexion')
  })

  it('marks the first onboarding step', () => {
    renderHome()

    expect(screen.getByRole('img', { name: 'Étape 1 sur 3' })).toBeInTheDocument()
  })
})
