import { cleanup, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it } from 'vitest'
import AppNav from './AppNav.tsx'

afterEach(cleanup)
describe('Main navigation', () => {
  it.each([
    ['/actualites', 'Fil'],
    ['/communaute/profils/camille', 'Fil'],
    ['/amis?liste=amis', 'Profil'],
    ['/collections/one', 'Profil'],
    ['/scanner', 'Scanner'],
  ])(
    'keeps the five main destinations and highlights the correct section on %s',
    (path, active) => {
      render(
        <MemoryRouter initialEntries={[path]}>
          <AppNav />
        </MemoryRouter>,
      )
      expect(screen.getAllByRole('link')).toHaveLength(5)
      expect(screen.getByRole('link', { name: active })).toHaveAttribute('aria-current', 'page')
      expect(screen.getByRole('link', { name: 'Profil' })).toHaveAttribute('href', '/profil')
      expect(screen.getByRole('link', { name: 'Dressing' })).toHaveAttribute('href', '/dressing')
    },
  )
})
