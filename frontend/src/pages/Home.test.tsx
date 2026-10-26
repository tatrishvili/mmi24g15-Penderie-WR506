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

  return render(page)
}

describe('Home', () => {
  afterEach(() => {
    cleanup()
  })

  it('renders the welcome onboarding screen', () => {
    const { container } = renderHome()

    expect(screen.getByRole('heading', { name: 'Bienvenue chez Penderie' })).toBeInTheDocument()
    expect(screen.getByText(/Ajoutez tous vos vêtements/)).toBeInTheDocument()
    expect(container.querySelector('.welcome__buddie')).toBeInTheDocument()
  })

  it('links to the next onboarding step and to sign-in', () => {
    renderHome()

    expect(screen.getByRole('link', { name: 'Suivant' })).toHaveAttribute('href', '/onboarding/2')
    expect(screen.getByRole('link', { name: 'Se connecter' })).toHaveAttribute('href', '/connexion')
  })

  it('marks the first onboarding step', () => {
    const { container } = renderHome()

    expect(screen.getByRole('img', { name: 'Étape 1 sur 4' })).toBeInTheDocument()
    expect(container.querySelectorAll('.dot')).toHaveLength(4)
  })
})
