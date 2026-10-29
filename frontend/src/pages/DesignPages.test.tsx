import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from '../auth/AuthContext.tsx'
import { stubFetch, jsonBody } from '../test/http.ts'
import HouseholdSetupPage from './HouseholdSetupPage.tsx'
import SocialProfilePage from './SocialProfilePage.tsx'
import ItemDetailPage, { AccessPage } from './ItemDetailPage.tsx'
import ItemEditorPage from './ItemEditorPage.tsx'
import LoansPage from './LoansPage.tsx'
import ScanPage from './ScanPage.tsx'
import { ItemForm } from './ItemFormPage.tsx'
import type { ReactNode } from 'react'

const ME = {
  user: { id: 1, email: 'private@example.com', username: 'marie' },
  households: [{ id: 1, name: 'Maison Martin', slug: 'maison' }],
  household: { id: 1, name: 'Maison Martin', slug: 'maison' },
  profiles: [{ id: 1, name: 'Moi', role: 'parent', hasPin: false, householdId: 1 }],
  places: [{ id: 1, name: 'Maison', isDefault: true }],
  activeHouseholdId: 1,
  activeProfileId: 1,
  activeProfile: { id: 1, name: 'Moi', role: 'parent' },
}
const ITEM = {
  id: 1,
  name: 'Pull noir',
  type: 'pull',
  color: 'Noir',
  status: 'disponible',
  condition: 'bon',
  visibility: 'private',
  ownerProfile: ME.profiles[0],
  photos: [],
  room: null,
  storageLocation: null,
}
const WORKSPACE = {
  profile: { displayName: 'Marie Martin', bio: 'Vinyles', photo: '' },
  username: 'marie',
  version: 1,
  collections: [
    { id: 'vinyles', name: 'Mes vinyles', cover: '', visibility: 'personal', itemIds: [1] },
  ],
  pins: ['vinyles'],
}
const LOAN = {
  id: 1,
  itemId: 1,
  itemName: 'Appareil photo',
  owner: 'marie',
  borrower: 'sophie',
  isOwner: true,
  status: 'pending',
  message: 'Pour le week-end',
  dueAt: '2099-11-18',
  requestedAt: '2099-11-10',
  acceptedAt: null,
  handedAt: null,
  returnedAt: null,
}
function response(url: string) {
  if (url.includes('/api/auth/me')) return { status: 200, body: ME }
  if (url.includes('/api/places'))
    return {
      status: 200,
      body: {
        places: [
          {
            id: 1,
            name: 'Maison',
            isDefault: true,
            rooms: [{ id: 1, name: 'Chambre', storageLocations: [] }],
          },
        ],
      },
    }
  if (url.endsWith('/api/social/workspace')) return { status: 200, body: WORKSPACE }
  if (url.endsWith('/api/items/1')) return { status: 200, body: { item: ITEM } }
  if (url.includes('/api/items')) return { status: 200, body: { items: [ITEM], total: 1 } }
  if (url.includes('/api/social/shared/'))
    return {
      status: 200,
      body: { item: { name: 'Appareil photo', owner: 'marie', available: true } },
    }
  if (url.endsWith('/api/social/shares')) return { status: 200, body: { shares: [] } }
  if (url.endsWith('/api/social/loans')) return { status: 200, body: { loans: [LOAN] } }
  return { status: 404, body: { error: { message: 'Indisponible' } } }
}
function mount(
  node: ReactNode,
  path = '/',
  route = path,
  handler: (url: string, init?: RequestInit) => { status: number; body: unknown } = response,
) {
  stubFetch(handler)
  return render(
    <MemoryRouter initialEntries={[path]}>
      <AuthProvider>
        <Routes>
          <Route path={route} element={node} />
          <Route path="/done" element={<p>Terminé</p>} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}
afterEach(() => {
  cleanup()
  vi.unstubAllGlobals()
})

describe('Current Figma page families', () => {
  it.each([
    ['create', 'Créer mon foyer'],
    ['members', 'Qui habite ici ?'],
    ['child', 'Ajouter un enfant'],
    ['rooms', 'Ajouter les pièces'],
    ['storage', 'Mes pièces et rangements'],
    ['overview', 'Maison Martin'],
    ['add-storage', 'Ajouter un rangement'],
  ] as const)('renders household %s and keeps the authenticated data', async (step, title) => {
    mount(<HouseholdSetupPage step={step} />)
    if (step === 'overview')
      expect(await screen.findByLabelText('Choisir un foyer')).toBeInTheDocument()
    else expect(await screen.findByRole('heading', { level: 1, name: title })).toBeInTheDocument()
    await waitFor(() => expect(screen.queryByText('Chargement…')).not.toBeInTheDocument())
  })
  it.each([
    ['profile', 'Mon profil'],
    ['edit', 'Modifier mon profil'],
    ['collections', 'Mes collections'],
    ['create', 'Créer une collection'],
    ['showcase', 'Personnaliser ma vitrine'],
  ] as const)('renders social %s', async (view, title) => {
    mount(<SocialProfilePage view={view} />)
    expect(await screen.findByRole('heading', { name: title })).toBeInTheDocument()
    await waitFor(() => expect(screen.queryByText('Chargement…')).not.toBeInTheDocument())
  })
  it.each([
    ['detail', 'Fiche article'],
    ['actions', 'Actions sur un article'],
    ['visibility', 'Visibilité d’un article'],
    ['share', 'Partager un article'],
    ['archive', 'Archiver ou supprimer'],
  ] as const)('renders article %s with real route IDs', async (view, title) => {
    mount(<ItemDetailPage view={view} />, '/articles/1', '/articles/:id')
    expect(await screen.findByRole('heading', { name: title })).toBeInTheDocument()
    await waitFor(() => expect(screen.queryByText('Chargement…')).not.toBeInTheDocument())
  })
  it.each([
    ['list', 'Mes prêts'],
    ['received', 'Demande reçue'],
    ['detail', 'Détail du prêt'],
    ['request', 'Demander un emprunt'],
  ] as const)('renders loan %s', async (view, title) => {
    mount(
      <LoansPage view={view} />,
      view === 'request' ? '/emprunter/token' : '/prets/1',
      view === 'request' ? '/emprunter/:token' : '/prets/:id',
    )
    expect(await screen.findByRole('heading', { name: title })).toBeInTheDocument()
    await waitFor(() => expect(screen.queryByText('Chargement…')).not.toBeInTheDocument())
  })
  it('renders access management without fabricated recipients', async () => {
    mount(<AccessPage />)
    expect(await screen.findByText('Aucun partage créé.')).toBeInTheDocument()
    expect(screen.queryByText('Sophie')).not.toBeInTheDocument()
  })
  it('renders the scanner and manual editor', async () => {
    const first = mount(<ScanPage />)
    expect(await screen.findByRole('heading', { name: 'Scanner un article' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Saisir manuellement' })).toBeInTheDocument()
    first.unmount()
    mount(<ItemEditorPage />)
    expect(await screen.findByRole('heading', { name: 'Nouvel article' })).toBeInTheDocument()
  })
  it('keeps a draft through the location step and submits the selected location', async () => {
    const saved = vi.fn()
    mount(<ItemForm onSubmit={saved} onClose={() => {}} />)
    fireEvent.change(screen.getByLabelText("Nom de l'article"), { target: { value: 'Pull bleu' } })
    fireEvent.click(screen.getByRole('button', { name: 'Choisir l’emplacement' }))
    expect(await screen.findByRole('heading', { name: 'Lieu de rangement' })).toBeInTheDocument()
    await screen.findByRole('option', { name: 'Chambre' })
    fireEvent.change(screen.getByLabelText('Pièce'), { target: { value: '1' } })
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer l’article' }))
    expect(saved).toHaveBeenCalledWith(
      expect.objectContaining({ name: 'Pull bleu', roomId: 1, storageLocationId: null }),
    )
  })
  it('requires share approval, sends the chosen account gate and displays only the returned link', async () => {
    let payload: unknown
    mount(
      <ItemDetailPage view="share" />,
      '/articles/1',
      '/articles/:id',
      (url, init?: RequestInit) => {
        if (url.endsWith('/shares') && init?.method === 'POST') {
          payload = jsonBody(init)
          return {
            status: 201,
            body: { path: '/partage/unguessable', id: 7, expiresAt: '2099-12-01' },
          }
        }
        return response(url)
      },
    )
    expect(screen.getByRole('button', { name: 'Partager' })).toBeDisabled()
    await screen.findByText('Pull noir')
    fireEvent.click(screen.getByRole('checkbox', { name: /Compte vérifié requis/ }))
    fireEvent.click(screen.getByRole('checkbox', { name: /J’approuve/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Partager' }))
    await screen.findByDisplayValue(/\/partage\/unguessable/)
    expect(payload).toEqual({ approved: true, verifiedOnly: true })
  })
  it('does not create a collection when the backend rejects it', async () => {
    mount(<SocialProfilePage view="create" />, '/', '/', (url, init?: RequestInit) =>
      init?.method === 'POST'
        ? { status: 403, body: { error: { message: 'Action refusée' } } }
        : response(url),
    )
    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Créer la collection' })).toBeEnabled(),
    )
    fireEvent.change(screen.getByLabelText('Nom de la collection'), { target: { value: 'Privée' } })
    fireEvent.click(screen.getByRole('button', { name: 'Créer la collection' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Action refusée')
  })
})
