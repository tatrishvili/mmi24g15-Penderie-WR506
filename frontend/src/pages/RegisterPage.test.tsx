import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from '../auth/AuthContext.tsx'
import RegisterPage from '../pages/RegisterPage.tsx'
import VerifyEmailPage from './VerifyEmailPage.tsx'
import { jsonBody, stubFetch, unauthorized } from '../test/http.ts'

const ME_ANON = unauthorized()

function renderRegister() {
  render(
    <MemoryRouter initialEntries={['/inscription']}>
      <AuthProvider>
        <Routes>
          <Route path="/inscription" element={<RegisterPage />} />
          <Route path="/verifier-email" element={<VerifyEmailPage />} />
          <Route path="/dressing" element={<p>Dressing</p>} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}

async function fillRequiredFields(email = 'camille@example.com') {
  fireEvent.change(await screen.findByLabelText('Nom d’utilisateur'), {
    target: { value: ' Camille_Moreau ' },
  })
  fireEvent.change(screen.getByLabelText('E-mail'), { target: { value: email } })
  fireEvent.change(screen.getByLabelText('Date de naissance'), {
    target: { value: '1990-05-12' },
  })
  fireEvent.change(screen.getByLabelText(/Mot de passe \(8 caractères/), {
    target: { value: 'secret123' },
  })
}

describe('RegisterPage', () => {
  afterEach(() => {
    cleanup()
  })

  it('renders the registration form', async () => {
    stubFetch((url) => (url.endsWith('/api/auth/me') ? ME_ANON : { status: 404, body: {} }))
    renderRegister()

    expect(await screen.findByLabelText('Nom d’utilisateur')).toBeInTheDocument()
    expect(screen.getByLabelText('E-mail')).toBeInTheDocument()
    expect(screen.getByLabelText('Date de naissance')).toHaveAttribute('type', 'date')
    expect(screen.getByLabelText(/Mot de passe \(8 caractères/)).toBeInTheDocument()
    expect(screen.getByText(/pas une vérification officielle/)).toBeInTheDocument()
    expect(screen.queryByLabelText('Votre prenom')).not.toBeInTheDocument()
  })

  it('sends the user to the verification page with their address after registering', async () => {
    stubFetch((url) => {
      if (url.endsWith('/api/auth/register')) {
        return { status: 202, body: { message: 'Verification email sent.' } }
      }
      if (url.endsWith('/api/auth/me')) return ME_ANON
      return { status: 404, body: {} }
    })
    renderRegister()

    await fillRequiredFields()
    fireEvent.click(screen.getByRole('button', { name: "S'inscrire" }))

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Vérifiez votre e-mail' }),
    ).toBeInTheDocument()
    expect(screen.getByText('camille@example.com')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Renvoyer le lien' })).toBeInTheDocument()
  })

  it('shows server-side validation errors on the right fields', async () => {
    stubFetch((url) => {
      if (url.endsWith('/api/auth/register')) {
        return {
          status: 422,
          body: {
            error: {
              code: 'validation_failed',
              message: 'Validation failed.',
              violations: { email: 'E-mail is not valid.' },
            },
          },
        }
      }
      return ME_ANON
    })
    renderRegister()

    await fillRequiredFields('bad')
    fireEvent.click(screen.getByRole('button', { name: "S'inscrire" }))

    expect(await screen.findByText('E-mail is not valid.')).toBeInTheDocument()
  })

  it('sends the trimmed payload to the API', async () => {
    let sent: Record<string, unknown> = {}

    stubFetch((url, init) => {
      if (url.endsWith('/api/auth/register')) {
        sent = init === undefined ? {} : jsonBody(init)
        return { status: 202, body: { message: 'Verification email sent.' } }
      }
      if (url.endsWith('/api/auth/me')) return ME_ANON
      return { status: 404, body: {} }
    })
    renderRegister()

    await fillRequiredFields('  camille@example.com ')
    fireEvent.click(screen.getByRole('button', { name: "S'inscrire" }))

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Vérifiez votre e-mail' }),
    ).toBeInTheDocument()
    expect(sent['username']).toBe('camille_moreau')
    expect(sent['email']).toBe('camille@example.com')
    expect(sent['birthDate']).toBe('1990-05-12')
    expect(sent['password']).toBe('secret123')
    expect(sent['passwordConfirm']).toBe('secret123')
    expect(sent['firstName']).toBeUndefined()
    expect(sent['householdName']).toBeUndefined()
    expect(sent['placeName']).toBeUndefined()
  })
})