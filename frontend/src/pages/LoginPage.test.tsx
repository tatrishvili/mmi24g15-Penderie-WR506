import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from '../auth/AuthContext.tsx'
import LoginPage from '../pages/LoginPage.tsx'
import ProfilesPage from '../pages/ProfilesPage.tsx'
import { stubFetch, unauthorized } from '../test/http.ts'

const ME_ANON = unauthorized()

const ME_PARENT = {
  status: 200,
  body: {
    household: { id: 1, name: 'Famille Moreau', slug: 'famille-moreau' },
    profiles: [
      { id: 1, name: 'Camille', role: 'parent', avatarColor: null, hasPin: false, householdId: 1 },
    ],
    places: [{ id: 1, name: 'Maison', isDefault: true }],
    activeProfileId: null,
    activeProfile: null,
    user: { id: 1, email: 'camille@example.com' },
  },
}

function renderLogin() {
  render(
    <MemoryRouter initialEntries={['/connexion']}>
      <AuthProvider>
        <Routes>
          <Route path="/connexion" element={<LoginPage />} />
          <Route path="/profils" element={<ProfilesPage />} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}

describe('LoginPage', () => {
  afterEach(() => {
    cleanup()
  })

  it('logs in with e-mail and password then lands on profiles', async () => {
    let meCalls = 0

    stubFetch((url) => {
      if (url.endsWith('/api/auth/login')) {
        return {
          status: 200,
          body: {
            user: { id: 1, email: 'camille@example.com' },
            household: ME_PARENT.body.household,
            profiles: ME_PARENT.body.profiles,
            activeProfileId: null,
          },
        }
      }
      if (url.endsWith('/api/auth/me')) {
        meCalls += 1
        return meCalls === 1 ? ME_ANON : ME_PARENT
      }
      return { status: 404, body: {} }
    })
    renderLogin()

    fireEvent.change(await screen.findByLabelText('E-mail'), {
      target: { value: 'camille@example.com' },
    })
    fireEvent.change(screen.getByLabelText('Mot de passe'), { target: { value: 'secret123' } })
    fireEvent.click(screen.getByRole('button', { name: 'Se connecter' }))

    await waitFor(() => {
      expect(screen.getByText('Qui entre ?')).toBeInTheDocument()
    })
  })

  it('shows an error on bad credentials', async () => {
    stubFetch((url) => {
      if (url.endsWith('/api/auth/login')) {
        return {
          status: 401,
          body: { error: { code: 'invalid_credentials', message: 'Invalid e-mail or password.' } },
        }
      }
      return ME_ANON
    })
    renderLogin()

    fireEvent.change(await screen.findByLabelText('E-mail'), {
      target: { value: 'camille@example.com' },
    })
    fireEvent.change(screen.getByLabelText('Mot de passe'), { target: { value: 'wrong' } })
    fireEvent.click(screen.getByRole('button', { name: 'Se connecter' }))

    expect(await screen.findByText('Invalid e-mail or password.')).toBeInTheDocument()
  })

  it('does not expose public child PIN sign-in', async () => {
    stubFetch(() => ME_ANON)
    renderLogin()

    expect(await screen.findByText(/Les enfants ne peuvent pas encore se connecter/)).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Entrer avec un code' })).not.toBeInTheDocument()
  })
})
