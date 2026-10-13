import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from '../auth/AuthContext.tsx'
import ProfilesPage from '../pages/ProfilesPage.tsx'
import RegisterPage from '../pages/RegisterPage.tsx'
import { jsonBody, stubFetch, unauthorized } from '../test/http.ts'

const ME_ANON = unauthorized()

const REGISTER_OK = {
  status: 201,
  body: {
    user: { id: 1, email: 'camille@example.com' },
    household: { id: 1, name: 'Famille Moreau', slug: 'famille-moreau' },
    profile: {
      id: 1,
      name: 'Camille',
      role: 'parent',
      avatarColor: null,
      hasPin: false,
      householdId: 1,
    },
    place: { id: 1, name: 'Maison', isDefault: true },
    activeProfileId: 1,
  },
}

const ME_PARENT = {
  status: 200,
  body: {
    household: { id: 1, name: 'Famille Moreau', slug: 'famille-moreau' },
    profiles: [
      { id: 1, name: 'Camille', role: 'parent', avatarColor: null, hasPin: false, householdId: 1 },
    ],
    places: [{ id: 1, name: 'Maison', isDefault: true }],
    activeProfileId: 1,
    activeProfile: {
      id: 1,
      name: 'Camille',
      role: 'parent',
      avatarColor: null,
      hasPin: false,
      householdId: 1,
    },
    user: { id: 1, email: 'camille@example.com' },
  },
}

function renderRegister() {
  render(
    <MemoryRouter initialEntries={['/inscription']}>
      <AuthProvider>
        <Routes>
          <Route path="/inscription" element={<RegisterPage />} />
          <Route path="/profils" element={<ProfilesPage />} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}

describe('RegisterPage', () => {
  afterEach(() => {
    cleanup()
  })

  it('renders the household creation form', async () => {
    stubFetch((url) => (url.endsWith('/api/auth/me') ? ME_ANON : { status: 404, body: {} }))
    renderRegister()

    expect(await screen.findByLabelText('E-mail')).toBeInTheDocument()
    expect(screen.getByLabelText(/Mot de passe \(8 caractères/)).toBeInTheDocument()
    expect(screen.getByLabelText('Nom du foyer')).toBeInTheDocument()
    expect(screen.getByLabelText('Votre prénom')).toBeInTheDocument()
  })

  it('warns when the password confirmation does not match', async () => {
    stubFetch(() => ME_ANON)
    renderRegister()

    fireEvent.change(await screen.findByLabelText(/Mot de passe \(8 caractères/), {
      target: { value: 'secret123' },
    })
    fireEvent.change(screen.getByLabelText('Confirmer le mot de passe'), {
      target: { value: 'different' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Créer mon foyer' }))

    expect(
      await screen.findByText('La confirmation ne correspond pas au mot de passe.'),
    ).toBeInTheDocument()
  })

  it('registers the household then lands on the profiles page', async () => {
    let meCalls = 0

    stubFetch((url) => {
      if (url.endsWith('/api/auth/register')) return REGISTER_OK
      if (url.endsWith('/api/auth/me')) {
        meCalls += 1
        return meCalls === 1 ? ME_ANON : ME_PARENT
      }
      return { status: 404, body: {} }
    })
    renderRegister()

    fireEvent.change(await screen.findByLabelText('E-mail'), {
      target: { value: 'camille@example.com' },
    })
    fireEvent.change(screen.getByLabelText(/Mot de passe \(8 caractères/), {
      target: { value: 'secret123' },
    })
    fireEvent.change(screen.getByLabelText('Confirmer le mot de passe'), {
      target: { value: 'secret123' },
    })
    fireEvent.change(screen.getByLabelText('Nom du foyer'), { target: { value: 'Famille Moreau' } })
    fireEvent.change(screen.getByLabelText('Votre prénom'), { target: { value: 'Camille' } })
    fireEvent.click(screen.getByRole('button', { name: 'Créer mon foyer' }))

    await waitFor(() => {
      expect(screen.getByText('Qui entre ?')).toBeInTheDocument()
    })
    expect(screen.getByText('Camille')).toBeInTheDocument()
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

    fireEvent.change(await screen.findByLabelText('E-mail'), { target: { value: 'bad' } })
    fireEvent.change(screen.getByLabelText(/Mot de passe \(8 caractères/), {
      target: { value: 'secret123' },
    })
    fireEvent.change(screen.getByLabelText('Confirmer le mot de passe'), {
      target: { value: 'secret123' },
    })
    fireEvent.change(screen.getByLabelText('Nom du foyer'), { target: { value: 'Fam' } })
    fireEvent.change(screen.getByLabelText('Votre prénom'), { target: { value: 'C' } })
    fireEvent.click(screen.getByRole('button', { name: 'Créer mon foyer' }))

    expect(await screen.findByText('E-mail is not valid.')).toBeInTheDocument()
  })

  it('sends the trimmed payload to the API', async () => {
    let sent: Record<string, unknown> = {}
    let meCalls = 0

    stubFetch((url, init) => {
      if (url.endsWith('/api/auth/register')) {
        sent = init === undefined ? {} : jsonBody(init)
        return REGISTER_OK
      }
      if (url.endsWith('/api/auth/me')) {
        meCalls += 1
        return meCalls === 1 ? ME_ANON : ME_PARENT
      }
      return { status: 404, body: {} }
    })
    renderRegister()

    fireEvent.change(await screen.findByLabelText('E-mail'), {
      target: { value: '  camille@example.com ' },
    })
    fireEvent.change(screen.getByLabelText(/Mot de passe \(8 caractères/), {
      target: { value: 'secret123' },
    })
    fireEvent.change(screen.getByLabelText('Confirmer le mot de passe'), {
      target: { value: 'secret123' },
    })
    fireEvent.change(screen.getByLabelText('Nom du foyer'), { target: { value: 'Famille Moreau' } })
    fireEvent.change(screen.getByLabelText('Votre prénom'), { target: { value: 'Camille' } })
    fireEvent.click(screen.getByRole('button', { name: 'Créer mon foyer' }))

    await waitFor(() => {
      expect(screen.getByText('Qui entre ?')).toBeInTheDocument()
    })
    expect(sent['email']).toBe('camille@example.com')
    expect(sent['householdName']).toBe('Famille Moreau')
  })
})
