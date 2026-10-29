import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from '../auth/AuthContext.tsx'
import { ProtectedRoute } from '../auth/ProtectedRoute.tsx'
import AccountPage from '../pages/AccountPage.tsx'
import LoginPage from '../pages/LoginPage.tsx'
import { unauthorized } from '../test/http.ts'
import { stubFetch } from '../test/http.ts'

const ME = {
  status: 200,
  body: {
    households: [{ id: 1, name: 'Famille Moreau', slug: 'famille-moreau' }],
    household: { id: 1, name: 'Famille Moreau', slug: 'famille-moreau' },
    profiles: [
      {
        id: 1,
        name: 'Camille',
        role: 'parent',
        avatarColor: null,
        hasPin: false,
        householdId: 1,
      },
      { id: 2, name: 'Léo', role: 'child', avatarColor: null, hasPin: true, householdId: 1 },
    ],
    places: [{ id: 1, name: 'Maison', isDefault: true }],
    activeHouseholdId: 1,
    activeProfileId: 1,
    activeProfile: {
      id: 1,
      name: 'Camille',
      role: 'parent',
      avatarColor: null,
      hasPin: false,
      householdId: 1,
    },
    user: { id: 1, email: 'camille@example.com', username: 'camille_moreau' },
  },
}

function renderAccount() {
  render(
    <MemoryRouter initialEntries={['/compte']}>
      <AuthProvider>
        <Routes>
          <Route
            path="/compte"
            element={
              <ProtectedRoute>
                <AccountPage />
              </ProtectedRoute>
            }
          />
          <Route path="/connexion" element={<LoginPage />} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}

describe('AccountPage', () => {
  afterEach(() => {
    cleanup()
  })

  it('shows every info about me with shortcuts', async () => {
    stubFetch((url) => (url.endsWith('/api/auth/me') ? ME : { status: 404, body: {} }))
    renderAccount()

    expect(await screen.findByText('Mon compte')).toBeInTheDocument()
    expect(screen.getByText('@camille_moreau')).toBeInTheDocument()
    expect(screen.getByText('camille@example.com')).toBeInTheDocument()
    expect(screen.getByText('Famille Moreau')).toBeInTheDocument()
    expect(screen.getAllByText('Camille')).toHaveLength(2)
    expect(screen.getByText('Léo')).toBeInTheDocument()
    expect(screen.getByText('famille-moreau')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Scanner un article/ })).toHaveAttribute(
      'href',
      '/scanner',
    )
    expect(screen.getByRole('link', { name: /Mon dressing/ })).toHaveAttribute(
      'href',
      '/dressing',
    )
  })

  it('lets an existing account without a username set one', async () => {
    let username: string | null = null
    stubFetch((url, init) => {
      if (url.endsWith('/api/auth/me')) {
        return {
          ...ME,
          body: { ...ME.body, user: { ...ME.body.user, username } },
        }
      }
      if (url.endsWith('/api/auth/username') && init?.method === 'PUT') {
        username = String(JSON.parse(String(init.body)).username)
        return { status: 200, body: { username } }
      }
      return { status: 404, body: {} }
    })
    renderAccount()

    fireEvent.change(await screen.findByLabelText('Nom d’utilisateur'), {
      target: { value: 'penderie_test' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(await screen.findByText('@penderie_test')).toBeInTheDocument()
    expect(screen.getByRole('status')).toHaveTextContent('Nom d’utilisateur enregistré.')
  })

  it('redirects anonymous users to the login page', async () => {
    stubFetch(() => unauthorized())
    renderAccount()

    expect(await screen.findByLabelText('E-mail')).toBeInTheDocument()
  })
})
