import { cleanup, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it } from 'vitest'
import { AuthContext } from '../auth/AuthContext.tsx'
import type { AuthContextValue } from '../auth/AuthContext.tsx'
import AppNav from './AppNav.tsx'

afterEach(cleanup)
const ADMIN_AUTH = {
  status: 'authenticated',
  user: { id: 1, email: 'admin@example.com', isSuperAdmin: true },
  households: [],
  household: null,
  profiles: [],
  places: [],
  activeHouseholdId: null,
  activeProfileId: null,
  activeProfile: null,
  refresh: async () => {},
  register: async () => {},
  verifyEmail: async () => {},
  resendVerification: async () => {},
  login: async () => {},
  unlockProfileWithPin: async () => {},
  logout: async () => {},
  switchProfile: async () => {},
  createProfile: async () => ({
    id: 1,
    name: 'Owner',
    role: 'parent',
    avatarColor: null,
    hasPin: false,
    householdId: 1,
  }),
  createHousehold: async () => {},
  selectHousehold: async () => {},
} satisfies AuthContextValue
const CHILD_AUTH = {
  ...ADMIN_AUTH,
  activeProfile: {
    id: 2,
    name: 'Léo',
    role: 'child',
    avatarColor: null,
    hasPin: false,
    householdId: 1,
  },
} satisfies AuthContextValue

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

  it('keeps My space inside Profile instead of adding a sixth adult tab', () => {
    render(
      <AuthContext.Provider value={ADMIN_AUTH}>
        <MemoryRouter initialEntries={['/my-space']}>
          <AppNav />
        </MemoryRouter>
      </AuthContext.Provider>,
    )

    expect(screen.getAllByRole('link')).toHaveLength(5)
    expect(screen.getByRole('link', { name: 'Profil' })).toHaveAttribute('href', '/profil')
    expect(screen.getByRole('link', { name: 'Profil' })).toHaveAttribute('aria-current', 'page')
    expect(screen.queryByRole('link', { name: 'Mon espace' })).not.toBeInTheDocument()
  })

  it('opens the child space from the child navigation', () => {
    render(
      <AuthContext.Provider value={CHILD_AUTH}>
        <MemoryRouter initialEntries={['/enfant']}>
          <AppNav />
        </MemoryRouter>
      </AuthContext.Provider>,
    )

    expect(screen.getByRole('link', { name: 'Mon espace' })).toHaveAttribute('href', '/enfant')
    expect(screen.getByRole('link', { name: 'Mon espace' })).toHaveAttribute('aria-current', 'page')
  })
})
