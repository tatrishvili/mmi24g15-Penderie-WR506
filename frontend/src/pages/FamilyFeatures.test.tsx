import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { AuthProvider } from '../auth/AuthContext.tsx'
import { ProtectedRoute } from '../auth/ProtectedRoute.tsx'
import FamilyVisibilityPanel from '../components/FamilyVisibilityPanel.tsx'
import RoomAccessPage from './RoomAccessPage.tsx'
import FamilyAccountsPage from './FamilyAccountsPage.tsx'
import NotificationsPage from './NotificationsPage.tsx'
import ItemDetailPage from './ItemDetailPage.tsx'
import LoginPage from './LoginPage.tsx'
import { jsonBody, stubFetch } from '../test/http.ts'
import type { ItemSummary } from '../types/item.ts'

const parent = {
  id: 1,
  name: 'Maman',
  role: 'parent',
  avatarColor: null,
  hasPin: false,
  householdId: 1,
}
const child = {
  id: 2,
  name: 'Emma',
  role: 'child',
  avatarColor: null,
  hasPin: false,
  householdId: 1,
}
const sibling = { ...child, id: 3, name: 'Lucas' }
const home = { id: 1, name: 'Maison', slug: 'maison' }
const me = {
  user: { id: 1, email: 'parent@example.com' },
  household: home,
  households: [home],
  profiles: [parent, child, sibling],
  places: [],
  activeProfile: parent,
  activeProfileId: 1,
  activeHouseholdId: 1,
}
const childMe = {
  ...me,
  user: undefined,
  profiles: [child],
  activeProfile: child,
  activeProfileId: 2,
  childAccount: { username: 'emma', mustChangePassword: false },
}
const item = {
  id: 8,
  name: 'Dessin',
  type: 'autre',
  category: null,
  brand: null,
  color: 'Bleu',
  size: null,
  material: null,
  condition: 'bon',
  status: 'disponible',
  visibility: 'private',
  qrToken: 'private-qr',
  notes: 'Private notes',
  memory: null,
  room: null,
  storageLocation: null,
  ownerProfile: child,
  photos: [],
  canEdit: true,
  familyAudience: 'owner',
  familyViewerIds: [],
  sharingVersion: 8,
} as ItemSummary
const bad = { status: 404, body: { error: { code: 'not_found', message: 'Not found' } } }
function mount(
  node: React.ReactNode,
  handler: (url: string, init?: RequestInit) => { status: number; body: unknown },
  profile: typeof me | typeof childMe = me,
  path = '/',
) {
  stubFetch((url, init) =>
    url === '/api/auth/me' ? { status: 200, body: profile } : handler(url, init),
  )
  return render(
    <MemoryRouter initialEntries={[path]}>
      <AuthProvider>{node}</AuthProvider>
    </MemoryRouter>,
  )
}
beforeEach(() => {
  document.documentElement.lang = 'fr'
})
afterEach(() => {
  cleanup()
  vi.unstubAllGlobals()
  document.documentElement.lang = 'fr'
})

describe('Family accounts and room permissions', () => {
  it('creates three separately named bedrooms with explicit assignments', async () => {
    const rooms: unknown[] = []
    const saved: Record<string, unknown>[] = []
    mount(<RoomAccessPage />, (url, init) => {
      if (url === '/api/places/1/rooms' && init?.method === 'POST') {
        const body = jsonBody(init)
        saved.push(body)
        const room = { ...body, id: rooms.length + 1, storageLocations: [] }
        rooms.push(room)
        return { status: 201, body: { room } }
      }
      if (url === '/api/places')
        return { status: 200, body: { places: [{ ...home, isDefault: true, rooms }] } }
      return bad
    })
    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Ajouter cette pièce' })).toBeEnabled(),
    )
    for (const name of ['Chambre des parents', 'Chambre d’Emma', 'Chambre de Lucas']) {
      fireEvent.change(screen.getByLabelText('Nom de cette pièce'), { target: { value: name } })
      fireEvent.click(screen.getByLabelText('Emma'))
      fireEvent.click(screen.getByRole('button', { name: 'Ajouter cette pièce' }))
      expect(await screen.findByText(name)).toBeInTheDocument()
      await waitFor(() =>
        expect(screen.getByRole('button', { name: 'Ajouter cette pièce' })).toBeEnabled(),
      )
    }
    expect(saved).toHaveLength(3)
    expect(saved.map((body) => body.kind)).toEqual(['bedroom', 'bedroom', 'bedroom'])
    expect(saved[0]).toMatchObject({ common: false, assignedProfileIds: [2] })
  })

  it('sends a child request without claiming that sharing is already approved', async () => {
    let saved: Record<string, unknown> = {}
    mount(
      <FamilyVisibilityPanel item={item} onSave={vi.fn()} />,
      (url, init) => {
        if (url === '/api/family/members')
          return { status: 200, body: { members: [parent, child, sibling] } }
        if (url.endsWith('/family-audience')) {
          saved = jsonBody(init)
          return { status: 202, body: { item, approval: { id: 4 } } }
        }
        return bad
      },
      childMe,
    )
    fireEvent.click(screen.getByLabelText('Personnes choisies'))
    fireEvent.click(await screen.findByLabelText('Lucas'))
    fireEvent.click(screen.getByRole('button', { name: 'Demander l’accord de mes parents' }))
    expect(await screen.findByRole('status')).toHaveTextContent(
      'Rien n’est visible avant leur accord',
    )
    expect(saved).toMatchObject({ audience: 'selected', profileIds: [3], version: 8 })
    expect(screen.queryByText('Private notes')).not.toBeInTheDocument()
  })

  it('hides editing and location controls for a shared read-only object', async () => {
    mount(
      <Routes>
        <Route path="/articles/:id" element={<ItemDetailPage view="detail" />} />
      </Routes>,
      (url) =>
        url === '/api/items/8'
          ? {
              status: 200,
              body: { item: { ...item, canEdit: false, room: null, notes: null, qrToken: null } },
            }
          : bad,
      me,
      '/articles/8',
    )
    expect(await screen.findByText(/Consultation uniquement/)).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Autres actions' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /Visibilité et partage/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /Rangement/ })).not.toBeInTheDocument()
  })

  it('creates an independent child account using the chosen parent relationship', async () => {
    let saved: Record<string, unknown> = {}
    mount(<FamilyAccountsPage />, (url, init) => {
      if (url === '/api/children' && init?.method === 'POST') {
        saved = jsonBody(init)
        return { status: 201, body: { profileId: 2, username: 'emma' } }
      }
      if (url === '/api/children') return { status: 200, body: { children: [] } }
      return bad
    })
    fireEvent.change(screen.getByLabelText('Prénom'), { target: { value: 'Emma' } })
    fireEvent.change(screen.getByLabelText('Âge (12 à 17 ans)'), { target: { value: '13' } })
    fireEvent.change(screen.getByLabelText('Je suis sa… / son…'), { target: { value: 'father' } })
    fireEvent.change(screen.getByLabelText('Nom d’utilisateur enfant'), {
      target: { value: 'emma' },
    })
    fireEvent.change(screen.getByLabelText('Mot de passe initial (15 caractères minimum)'), {
      target: { value: 'Long initial password for Emma' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Créer l’accès enfant' }))
    expect(await screen.findByRole('status')).toHaveTextContent('Compte créé')
    expect(saved).toMatchObject({ name: 'Emma', age: 13, username: 'emma', relationship: 'father' })
    expect(screen.getByLabelText('Mot de passe initial (15 caractères minimum)')).toHaveValue('')
  })
})

describe('Approval inbox', () => {
  const approval = {
    id: 5,
    version: 2,
    kind: 'family_item',
    childName: 'Emma',
    status: 'pending',
    canDecide: true,
    proposal: {
      recipients: [{ id: 3, name: 'Lucas' }],
      preview: { name: 'Dessin', type: 'autre', color: 'Bleu', photos: [] },
    },
  }
  it('keeps reading separate from approving and sends the reviewed request version', async () => {
    const calls: { url: string; body: Record<string, unknown> }[] = []
    let read = false
    let approved = false
    mount(<NotificationsPage />, (url, init) => {
      if (init?.method === 'POST') calls.push({ url, body: jsonBody(init) })
      if (url === '/api/notifications/9/read') {
        read = true
        return { status: 200, body: { read: true } }
      }
      if (url === '/api/child-approvals/5/decision') {
        approved = true
        return { status: 200, body: {} }
      }
      if (url === '/api/notifications')
        return {
          status: 200,
          body: {
            unread: read ? 0 : 1,
            nextBefore: null,
            notifications: [
              {
                id: 9,
                kind: 'approval',
                read,
                referenceId: 5,
                createdAt: '',
                detail: {
                  ...approval,
                  canDecide: !approved,
                  status: approved ? 'approved' : 'pending',
                },
              },
            ],
          },
        }
      return bad
    })
    fireEvent.click(await screen.findByRole('button', { name: 'Marquer comme lu' }))
    await waitFor(() =>
      expect(screen.queryByRole('button', { name: 'Marquer comme lu' })).not.toBeInTheDocument(),
    )
    expect(approved).toBe(false)
    expect(screen.getByText(/Lucas/)).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Approuver cet aperçu' }))
    await waitFor(() => expect(screen.getByText('Approuvé')).toBeInTheDocument())
    expect(calls.find((call) => call.url.endsWith('/decision'))?.body).toEqual({
      approve: true,
      version: 2,
    })
  })

  it('reports a stale approval instead of claiming publication succeeded', async () => {
    mount(<NotificationsPage />, (url) => {
      if (url === '/api/notifications')
        return {
          status: 200,
          body: {
            unread: 1,
            nextBefore: null,
            notifications: [
              { id: 9, kind: 'approval', read: false, referenceId: 5, detail: approval },
            ],
          },
        }
      if (url.endsWith('/decision'))
        return {
          status: 409,
          body: { error: { code: 'conflict', message: 'Rechargez cet aperçu avant de décider.' } },
        }
      return bad
    })
    fireEvent.click(await screen.findByRole('button', { name: 'Approuver cet aperçu' }))
    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Rechargez cet aperçu avant de décider.',
    )
    expect(screen.queryByText('Décision enregistrée.')).not.toBeInTheDocument()
  })
})

it('uses child username login rather than adult email login', async () => {
  const calls: string[] = []
  stubFetch((url) => {
    calls.push(url)
    if (url === '/api/auth/me')
      return { status: 401, body: { error: { code: 'unauthenticated', message: 'Sign in' } } }
    return {
      status: 401,
      body: { error: { code: 'invalid_credentials', message: 'Invalid username or password.' } },
    }
  })
  render(
    <MemoryRouter>
      <AuthProvider>
        <LoginPage />
      </AuthProvider>
    </MemoryRouter>,
  )
  fireEvent.click(screen.getByRole('button', { name: 'Connexion enfant' }))
  fireEvent.change(screen.getByLabelText('Nom d’utilisateur enfant'), { target: { value: 'emma' } })
  fireEvent.change(screen.getByLabelText('Mot de passe'), {
    target: { value: 'A long private password' },
  })
  await waitFor(() => expect(screen.getByRole('button', { name: 'Se connecter' })).toBeEnabled())
  fireEvent.click(screen.getByRole('button', { name: 'Se connecter' }))
  await waitFor(() => expect(calls).toContain('/api/auth/child-login'))
  expect(calls).not.toContain('/api/auth/login')
})

it('redirects a child away from adult household management', async () => {
  stubFetch(() => ({ status: 200, body: childMe }))
  render(
    <MemoryRouter initialEntries={['/foyer/creer']}>
      <AuthProvider>
        <Routes>
          <Route
            path="/foyer/creer"
            element={
              <ProtectedRoute>
                <p>Adult management</p>
              </ProtectedRoute>
            }
          />
          <Route path="/enfant" element={<p>Child space</p>} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
  expect(await screen.findByText('Child space')).toBeInTheDocument()
  expect(screen.queryByText('Adult management')).not.toBeInTheDocument()
})
