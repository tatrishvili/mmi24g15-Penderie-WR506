import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from '../auth/AuthContext.tsx'
import { ProtectedRoute } from '../auth/ProtectedRoute.tsx'
import ItemsPage from '../pages/ItemsPage.tsx'
import LoginPage from '../pages/LoginPage.tsx'
import { stubFetch, unauthorized } from '../test/http.ts'

const ITEM = {
  id: 1,
  name: 'Pull noir Zara',
  brand: 'Zara',
  color: 'noir',
  size: 'M',
  material: null,
  type: null,
  category: null,
  room: null,
  storageLocation: null,
  condition: 'tres_bon',
  status: 'disponible',
  qrToken: 'abc123',
  memory: null,
  notes: null,
  ownerProfile: {
    id: 1,
    name: 'Camille',
    role: 'parent',
    avatarColor: null,
    hasPin: false,
    householdId: 1,
  },
  photos: [],
}

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

function renderDressing(handler: (url: string) => { status: number; body: unknown }) {
  stubFetch(handler)
  render(
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
          <Route path="/connexion" element={<LoginPage />} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}

describe('ItemsPage', () => {
  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
  })

  it('lists the foyer items', async () => {
    renderDressing((url) => {
      if (url.endsWith('/api/auth/me')) return ME
      if (url.includes('/api/items')) {
        return { status: 200, body: { items: [ITEM], total: 1, page: 1, perPage: 20 } }
      }
      return { status: 404, body: {} }
    })

    expect(await screen.findByText('Pull noir Zara')).toBeInTheDocument()
    expect(screen.getByText(/1 article/)).toBeInTheDocument()
  })

  it('shows an empty state with a scanner shortcut', async () => {
    renderDressing((url) => {
      if (url.endsWith('/api/auth/me')) return ME
      if (url.includes('/api/items')) {
        return { status: 200, body: { items: [], total: 0, page: 1, perPage: 20 } }
      }
      return { status: 404, body: {} }
    })

    expect(await screen.findByText(/dressing est encore vide/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Scanner un article/ })).toHaveAttribute(
      'href',
      '/scanner',
    )
  })

  it('sends the search query to the API', async () => {
    const calls: string[] = []
    renderDressing((url) => {
      calls.push(url)
      if (url.endsWith('/api/auth/me')) return ME
      if (url.includes('/api/items')) {
        return { status: 200, body: { items: [], total: 0, page: 1, perPage: 20 } }
      }
      return { status: 404, body: {} }
    })

    fireEvent.change(await screen.findByLabelText('Rechercher'), { target: { value: 'noir' } })
    fireEvent.click(screen.getByRole('button', { name: 'Rechercher' }))

    await screen.findByText(/dressing est encore vide/)
    expect(calls.some((url) => url.includes('/api/items?q=noir'))).toBe(true)
  })

  it('redirects anonymous users to the login page', async () => {
    renderDressing(() => unauthorized())

    expect(await screen.findByLabelText('E-mail')).toBeInTheDocument()
  })
})
