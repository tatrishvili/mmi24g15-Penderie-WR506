import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from '../auth/AuthContext.tsx'
import { ProtectedRoute } from '../auth/ProtectedRoute.tsx'
import { expectNoA11yViolations } from '../test/a11y.ts'
import { stubFetch, unauthorized } from '../test/http.ts'
import Home from './Home.tsx'
import ItemsPage from './ItemsPage.tsx'
import LoginPage from './LoginPage.tsx'
import RegisterPage from './RegisterPage.tsx'

const ME = {
  status: 200,
  body: {
    households: [{ id: 1, name: 'Famille Moreau', slug: 'famille-moreau' }],
    household: { id: 1, name: 'Famille Moreau', slug: 'famille-moreau' },
    profiles: [],
    places: [],
    activeHouseholdId: 1,
    activeProfileId: 1,
    activeProfile: null,
    user: { id: 1, email: 'camille@example.com' },
  },
}

describe('Accessibility (axe-core)', () => {
  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
  })

  it('landing page: no violations', async () => {
    stubFetch((url) =>
      url.endsWith('/api/health')
        ? { status: 200, body: { status: 'ok' } }
        : { status: 404, body: {} },
    )
    const { container } = render(
      <MemoryRouter>
        <Home />
      </MemoryRouter>,
    )

    expect(
      await screen.findByText('Votre dressing, dans votre téléphone.', { exact: false }),
    ).toBeInTheDocument()

    await expectNoA11yViolations(container)
  })

  it('login form: labels are wired, errors are announced, no violations', async () => {
    stubFetch((url) => {
      if (url.endsWith('/api/auth/login')) {
        return {
          status: 401,
          body: { error: { code: 'invalid_credentials', message: 'Invalid e-mail or password.' } },
        }
      }
      return unauthorized()
    })
    const { container } = render(
      <MemoryRouter initialEntries={['/connexion']}>
        <AuthProvider>
          <Routes>
            <Route path="/connexion" element={<LoginPage />} />
          </Routes>
        </AuthProvider>
      </MemoryRouter>,
    )

    fireEvent.change(await screen.findByLabelText('E-mail'), {
      target: { value: 'camille@example.com' },
    })
    fireEvent.change(screen.getByLabelText('Mot de passe'), { target: { value: 'wrong' } })
    fireEvent.click(screen.getByRole('button', { name: 'Se connecter' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Invalid e-mail or password.')

    await expectNoA11yViolations(container)
  })

  it('registration form: every field has a label, no violations', async () => {
    stubFetch((url) => (url.endsWith('/api/auth/me') ? unauthorized() : { status: 404, body: {} }))
    const { container } = render(
      <MemoryRouter initialEntries={['/inscription']}>
        <AuthProvider>
          <Routes>
            <Route path="/inscription" element={<RegisterPage />} />
          </Routes>
        </AuthProvider>
      </MemoryRouter>,
    )

    expect(await screen.findByLabelText('Nom d’utilisateur')).toBeInTheDocument()
    expect(screen.getByLabelText('E-mail')).toBeInTheDocument()
    expect(screen.getByLabelText('Date de naissance')).toBeInTheDocument()
    expect(screen.getByLabelText(/Mot de passe/)).toBeInTheDocument()

    await expectNoA11yViolations(container)
  })

  it('inventory page (authenticated): no violations', async () => {
    stubFetch((url) => {
      if (url.endsWith('/api/auth/me')) return ME
      if (url.includes('/api/items')) {
        return { status: 200, body: { items: [], total: 0, page: 1, perPage: 20 } }
      }
      return { status: 404, body: {} }
    })
    const { container } = render(
      <MemoryRouter initialEntries={['/dressing']}>
        <AuthProvider>
          <Routes>
            <Route
              path="/dressing"
              element={
                <ProtectedRoute>
                  <ItemsPage />
                </ProtectedRoute>
              }
            />
          </Routes>
        </AuthProvider>
      </MemoryRouter>,
    )

    expect(await screen.findByText(/dressing est encore vide/)).toBeInTheDocument()

    await expectNoA11yViolations(container)
  })
})
