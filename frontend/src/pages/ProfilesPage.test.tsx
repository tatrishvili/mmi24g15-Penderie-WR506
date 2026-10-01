import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from '../auth/AuthContext.tsx'
import { ProtectedRoute } from '../auth/ProtectedRoute.tsx'
import LoginPage from '../pages/LoginPage.tsx'
import ProfilesPage from '../pages/ProfilesPage.tsx'
import { stubFetch, unauthorized } from '../test/http.ts'

const PARENT = {
  id: 1,
  name: 'Camille',
  role: 'parent',
  avatarColor: null,
  hasPin: false,
  householdId: 1,
}
const CHILD = { id: 2, name: 'Léo', role: 'child', avatarColor: null, hasPin: true, householdId: 1 }
const HOUSEHOLD = { id: 1, name: 'Famille Moreau', slug: 'famille-moreau' }
const PLACES = [{ id: 1, name: 'Maison', isDefault: true }]

function meWith(profiles: unknown[], activeId: number | null) {
  const active = (profiles as (typeof PARENT)[]).find((profile) => profile.id === activeId) ?? null

  return {
    status: 200,
    body: {
      household: HOUSEHOLD,
      profiles,
      places: PLACES,
      activeProfileId: activeId,
      activeProfile: active,
      user: { id: 1, email: 'camille@example.com' },
    },
  }
}

function renderProfiles() {
  render(
    <MemoryRouter initialEntries={['/profils']}>
      <AuthProvider>
        <Routes>
          <Route
            path="/profils"
            element={
              <ProtectedRoute>
                <ProfilesPage />
              </ProtectedRoute>
            }
          />
          <Route path="/connexion" element={<LoginPage />} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}

describe('ProfilesPage', () => {
  afterEach(() => {
    cleanup()
  })

  it('lists household profiles with the active one checked', async () => {
    stubFetch((url) =>
      url.endsWith('/api/auth/me') ? meWith([PARENT, CHILD], 1) : { status: 404, body: {} },
    )
    renderProfiles()

    expect(await screen.findByText('Camille')).toBeInTheDocument()
    expect(screen.getByText('Léo')).toBeInTheDocument()
    expect(screen.getByText(/famille-moreau/)).toBeInTheDocument()
    expect(screen.getByLabelText('Profil actif')).toBeInTheDocument()
  })

  it('switches directly to a profile without PIN', async () => {
    let meCalls = 0
    let switched: number | null = null
    const calls: string[] = []

    stubFetch((url) => {
      calls.push(url)
      if (url.endsWith('/api/auth/me')) {
        meCalls += 1
        return meCalls === 1 ? meWith([PARENT, CHILD], null) : meWith([PARENT, CHILD], switched)
      }
      if (url.includes('/switch')) {
        switched = 1
        return { status: 200, body: { profile: PARENT, activeProfileId: 1 } }
      }
      return { status: 404, body: {} }
    })
    renderProfiles()

    fireEvent.click(await screen.findByRole('button', { name: /Camille/ }))

    await waitFor(() => {
      expect(screen.getByLabelText('Profil actif')).toBeInTheDocument()
    })
    expect(calls.some((url) => url.endsWith('/api/auth/profiles/1/switch'))).toBe(true)
  })

  it('asks for the PIN on a PIN-protected profile then enters', async () => {
    let meCalls = 0

    stubFetch((url) => {
      if (url.endsWith('/api/auth/me')) {
        meCalls += 1
        return meCalls === 1 ? meWith([PARENT, CHILD], 1) : meWith([PARENT, CHILD], 2)
      }
      if (url.endsWith('/api/auth/profile-pin')) {
        return { status: 200, body: { profile: CHILD, household: HOUSEHOLD, activeProfileId: 2 } }
      }
      return { status: 404, body: {} }
    })
    renderProfiles()

    fireEvent.click(await screen.findByRole('button', { name: /Léo/ }))

    const pinLabel = await screen.findByLabelText('Code PIN de Léo')
    fireEvent.change(pinLabel, { target: { value: '1234' } })

    const pinForm = pinLabel.closest('form')
    if (pinForm === null) throw new Error('PIN form not found')
    fireEvent.click(within(pinForm).getByRole('button', { name: 'Entrer' }))

    await waitFor(() => {
      const activeRow = screen.getByLabelText('Profil actif').closest('button')
      expect(activeRow).toHaveTextContent('Léo')
    })
  })

  it('creates a child profile from the adult view', async () => {
    let meCalls = 0
    let created = false

    stubFetch((url) => {
      if (url.endsWith('/api/auth/me')) {
        meCalls += 1
        if (meCalls === 1) return meWith([PARENT], 1)
        return created ? meWith([PARENT, CHILD], 1) : meWith([PARENT], 1)
      }
      if (url.endsWith('/api/auth/profiles')) {
        created = true
        return { status: 201, body: { profile: CHILD } }
      }
      return { status: 404, body: {} }
    })
    renderProfiles()

    fireEvent.click(await screen.findByRole('button', { name: /Enfant/ }))
    fireEvent.change(screen.getByLabelText('Prénom de l’enfant'), { target: { value: 'Léo' } })
    fireEvent.change(screen.getByLabelText(/Code PIN \(4 à 6 chiffres\)/), {
      target: { value: '1234' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter l’enfant' }))

    await waitFor(() => {
      expect(screen.getByText('Léo')).toBeInTheDocument()
    })
  })

  it('hides management buttons for a child-active session', async () => {
    stubFetch((url) =>
      url.endsWith('/api/auth/me') ? meWith([PARENT, CHILD], 2) : { status: 404, body: {} },
    )
    renderProfiles()

    expect(await screen.findByText('Léo')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Enfant' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Conjoint' })).not.toBeInTheDocument()
  })

  it('redirects anonymous users to the login page', async () => {
    stubFetch(() => unauthorized())
    renderProfiles()

    expect(await screen.findByRole('tab', { name: 'E-mail' })).toBeInTheDocument()
  })
})
