import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from '../auth/AuthContext.tsx'
import VerifyEmailPage from './VerifyEmailPage.tsx'
import { jsonBody, stubFetch, unauthorized } from '../test/http.ts'

const ME_ANON = unauthorized()

const ME_AUTHENTICATED = {
  status: 200,
  body: {
    households: [{ id: 1, name: 'Mon dressing', slug: 'mon-dressing' }],
    household: { id: 1, name: 'Mon dressing', slug: 'mon-dressing' },
    profiles: [{ id: 1, name: 'Moi', role: 'parent', avatarColor: null, hasPin: false, householdId: 1 }],
    places: [{ id: 1, name: 'Maison', isDefault: true }],
    activeHouseholdId: 1,
    activeProfileId: 1,
    activeProfile: { id: 1, name: 'Moi', role: 'parent', avatarColor: null, hasPin: false, householdId: 1 },
    user: { id: 1, email: 'camille@example.com' },
  },
}

function renderVerify(initialEntry = '/verifier-email?token=abc') {
  render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <AuthProvider>
        <Routes>
          <Route path="/verifier-email" element={<VerifyEmailPage />} />
          <Route path="/dressing" element={<p>Compte prêt</p>} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}

describe('VerifyEmailPage', () => {
  afterEach(() => cleanup())

  it('confirms with POST and signs the user in', async () => {
    let verified = false
    let submitted: Record<string, unknown> = {}
    stubFetch((url, init) => {
      if (url.endsWith('/api/auth/me')) return verified ? ME_AUTHENTICATED : ME_ANON
      if (url.endsWith('/api/auth/verify-email')) {
        submitted = jsonBody(init)
        verified = true
        return { status: 200, body: ME_AUTHENTICATED.body }
      }
      return { status: 404, body: {} }
    })
    renderVerify()

    await screen.findByText(/Votre mot de passe n'est jamais envoyé/)
    fireEvent.click(screen.getByRole('button', { name: 'Confirmer et créer mon compte' }))

    expect(await screen.findByText('Compte prêt')).toBeInTheDocument()
    expect(submitted['token']).toBe('abc')
    expect(submitted['password']).toBeUndefined()
  })

  it('offers a resend form when the token is expired', async () => {
    let resentEmail = ''
    stubFetch((url, init) => {
      if (url.endsWith('/api/auth/me')) return ME_ANON
      if (url.endsWith('/api/auth/verify-email')) {
        return {
          status: 400,
          body: { error: { code: 'bad_request', message: 'Expired.' } },
        }
      }
      if (url.endsWith('/api/auth/resend-verification')) {
        resentEmail = String(jsonBody(init)['email'])
        return { status: 202, body: { message: 'If pending, email sent.' } }
      }
      return { status: 404, body: {} }
    })
    renderVerify()

    await screen.findByRole('button', { name: 'Confirmer et créer mon compte' })
    fireEvent.click(screen.getByRole('button', { name: 'Confirmer et créer mon compte' }))
    expect(await screen.findByRole('alert')).toHaveTextContent(/invalide ou a expiré/)

    fireEvent.change(screen.getByLabelText('E-mail'), {
      target: { value: 'camille@example.com' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Renvoyer le lien' }))

    await waitFor(() => expect(resentEmail).toBe('camille@example.com'))
    expect(await screen.findByRole('status')).toHaveTextContent(/nouveau lien/)
  })
})
