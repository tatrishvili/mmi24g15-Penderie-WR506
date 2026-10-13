import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from '../auth/AuthContext.tsx'
import { expectNoA11yViolations } from '../test/a11y.ts'
import { stubFetch, unauthorized } from '../test/http.ts'
import Home from './Home.tsx'
import LoginPage from './LoginPage.tsx'
import RegisterPage from './RegisterPage.tsx'

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

    await waitFor(() => {
      expect(container.querySelectorAll('input, select, textarea').length).toBeGreaterThan(0)
    })

    container.querySelectorAll('input, select, textarea').forEach((field) => {
      expect(field).toHaveAccessibleName()
    })

    await expectNoA11yViolations(container)
  })
})
