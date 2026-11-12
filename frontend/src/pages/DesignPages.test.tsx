import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from '../auth/AuthContext.tsx'
import { stubFetch, jsonBody } from '../test/http.ts'
import HouseholdSetupPage from './HouseholdSetupPage.tsx'
import CommunityCollectionsPage from './CommunityCollectionsPage.tsx'
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
    ['child', 'Comptes enfants'],
    ['rooms', 'Pièces du foyer'],
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
  it('shows real connection counts with links to each own social list', async () => {
    mount(<SocialProfilePage view="profile" />, '/', '/', (url) =>
      url.endsWith('/workspace')
        ? {
            status: 200,
            body: { ...WORKSPACE, connections: { friends: 3, followers: 7, following: 5 } },
          }
        : response(url),
    )
    expect(await screen.findByRole('link', { name: '3 Amis' })).toHaveAttribute(
      'href',
      '/amis?liste=amis',
    )
    expect(screen.getByRole('link', { name: '7 Abonnés' })).toHaveAttribute(
      'href',
      '/amis?liste=abonnes',
    )
    expect(screen.getByRole('link', { name: '5 Abonnements' })).toHaveAttribute(
      'href',
      '/amis?liste=abonnements',
    )
    expect(screen.getByRole('link', { name: /Famille et profils/ })).toHaveAttribute(
      'href',
      '/foyer/membres',
    )
  })
  it('shows all own collections, filters private/shared content and persists pins directly', async () => {
    let workspace = {
      ...WORKSPACE,
      pins: [] as string[],
      collections: [
        { id: 'private', name: 'Mes souvenirs', cover: '', visibility: 'personal', itemIds: [] },
        { id: 'shared', name: 'Mes trouvailles', cover: '', visibility: 'friends', itemIds: [1] },
      ],
    }
    let saved: Record<string, unknown> = {}
    const handler = (url: string, init?: RequestInit) => {
      if (url.endsWith('/workspace')) {
        if (init?.method === 'PATCH') {
          saved = jsonBody(init)
          workspace = { ...workspace, version: workspace.version + 1, pins: saved.pins as string[] }
        }
        return { status: 200, body: workspace }
      }
      return response(url)
    }
    const page = mount(<SocialProfilePage view="profile" />, '/', '/', handler)
    expect(await screen.findByRole('article', { name: 'Mes souvenirs' })).toBeInTheDocument()
    expect(screen.getByRole('article', { name: 'Mes trouvailles' })).toBeInTheDocument()
    expect(
      within(screen.getByRole('article', { name: 'Mes souvenirs' })).getByText('Privée'),
    ).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Partagées' }))
    expect(screen.queryByRole('article', { name: 'Mes souvenirs' })).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Toutes' }))
    fireEvent.click(screen.getByRole('button', { name: 'Épingler Mes trouvailles' }))
    expect(
      await screen.findByRole('button', { name: 'Désépingler Mes trouvailles' }),
    ).toHaveAttribute('aria-pressed', 'true')
    expect(saved).toEqual({ version: 1, pins: ['shared'] })
    expect(screen.getAllByRole('article')[0]).toHaveAccessibleName('Mes trouvailles')
    page.unmount()
    mount(<SocialProfilePage view="profile" />, '/', '/', handler)
    expect(
      await screen.findByRole('button', { name: 'Désépingler Mes trouvailles' }),
    ).toHaveAttribute('aria-pressed', 'true')
    fireEvent.click(screen.getByRole('button', { name: 'Désépingler Mes trouvailles' }))
    expect(await screen.findByRole('button', { name: 'Épingler Mes trouvailles' })).toHaveAttribute(
      'aria-pressed',
      'false',
    )
    fireEvent.click(screen.getByRole('button', { name: 'Privées' }))
    expect(screen.getByRole('article', { name: 'Mes souvenirs' })).toBeInTheDocument()
    expect(screen.queryByRole('article', { name: 'Mes trouvailles' })).not.toBeInTheDocument()
  })
  it('leaves a collection unpinned and reports a failed save', async () => {
    mount(<SocialProfilePage view="profile" />, '/', '/', (url, init) => {
      if (url.endsWith('/workspace') && init?.method === 'PATCH')
        return {
          status: 409,
          body: { error: { code: 'conflict', message: 'Rechargez le profil.' } },
        }
      if (url.endsWith('/workspace')) return { status: 200, body: { ...WORKSPACE, pins: [] } }
      return response(url)
    })
    fireEvent.click(await screen.findByRole('button', { name: 'Épingler Mes vinyles' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Rechargez le profil.')
    expect(screen.getByRole('button', { name: 'Épingler Mes vinyles' })).toHaveAttribute(
      'aria-pressed',
      'false',
    )
  })
  it('keeps name and photo sharing opt-in when saving a profile', async () => {
    let saved: Record<string, unknown> = {}
    mount(<SocialProfilePage view="edit" />, '/', '/', (url, init) => {
      if (url.endsWith('/workspace') && init?.method === 'PATCH') {
        saved = jsonBody(init)
        return { status: 200, body: WORKSPACE }
      }
      return response(url)
    })
    const consent = await screen.findByRole('checkbox', {
      name: 'Afficher ce nom et cette photo aux membres Penderie',
    })
    expect(consent).not.toBeChecked()
    const bioConsent = screen.getByRole('checkbox', {
      name: 'Afficher aussi ma bio sur mon profil Penderie',
    })
    expect(bioConsent).toBeDisabled()
    expect(bioConsent).not.toBeChecked()
    fireEvent.click(consent)
    expect(bioConsent).toBeEnabled()
    fireEvent.click(bioConsent)
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))
    await waitFor(() =>
      expect(saved).toMatchObject({
        shareIdentity: true,
        shareBio: true,
        profile: WORKSPACE.profile,
      }),
    )
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

describe('Collection audiences', () => {
  const preview = {
    digest: 'approved-digest',
    name: 'Mes vinyles',
    cover: '',
    items: [{ name: 'Disque approuvé', type: 'vinyle', color: null, photoUrl: null }],
    excludedCount: 1,
  }
  it('sends the chosen creation audience with explicit approval', async () => {
    let payload: Record<string, unknown> = {}
    mount(<SocialProfilePage view="create" />, '/', '/', (url, init) => {
      if (url.endsWith('/api/social/collections') && init?.method === 'POST') {
        payload = jsonBody(init)
        return { status: 200, body: WORKSPACE }
      }
      return response(url)
    })
    fireEvent.change(await screen.findByLabelText('Nom de la collection'), {
      target: { value: 'Mes disques' },
    })
    const visibility = screen.getByLabelText('Visibilité')
    expect(visibility).toHaveValue('personal')
    for (const audience of ['friends', 'followers', 'app']) {
      fireEvent.change(visibility, { target: { value: audience } })
      expect(visibility).toHaveValue(audience)
    }
    fireEvent.click(screen.getByRole('checkbox', { name: /J’approuve/ }))
    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Créer la collection' })).toBeEnabled(),
    )
    fireEvent.click(screen.getByRole('button', { name: 'Créer la collection' }))
    await waitFor(() =>
      expect(payload).toMatchObject({ name: 'Mes disques', visibility: 'app', approved: true }),
    )
  })
  it('publishes the approved preview directly from the sharing button and confirms success', async () => {
    let payload: Record<string, unknown> = {}
    mount(
      <SocialProfilePage view="collection" />,
      '/collections/vinyles',
      '/collections/:collectionId',
      (url, init) => {
        if (url.endsWith('/preview')) return { status: 200, body: preview }
        if (url.endsWith('/audience')) {
          payload = jsonBody(init)
          return { status: 200, body: { visibility: 'friends', version: 2 } }
        }
        if (url.endsWith('/workspace') && payload.approved) {
          return {
            status: 200,
            body: {
              ...WORKSPACE,
              version: 2,
              collections: [{ ...WORKSPACE.collections[0], visibility: 'friends' }],
            },
          }
        }
        return response(url)
      },
    )
    fireEvent.change(await screen.findByLabelText('Visibilité'), { target: { value: 'friends' } })
    expect(await screen.findByText('Disque approuvé')).toBeInTheDocument()
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
    expect(payload).toEqual({})
    fireEvent.click(screen.getByRole('button', { name: 'Partager avec mes amis' }))
    await waitFor(() =>
      expect(payload).toEqual({
        version: 1,
        visibility: 'friends',
        approved: true,
        digest: 'approved-digest',
      }),
    )
    expect(await screen.findByText('Collection partagée avec succès.')).toBeInTheDocument()
    expect(screen.getByLabelText('Visibilité')).toHaveValue('friends')
    expect(screen.getByRole('link', { name: 'Voir la collection partagée' })).toHaveAttribute(
      'href',
      '/communaute/collections/vinyles',
    )
  })
  it('shows a publication failure beside the sharing action without claiming success', async () => {
    mount(
      <SocialProfilePage view="collection" />,
      '/collections/vinyles',
      '/collections/:collectionId',
      (url) => {
        if (url.endsWith('/preview')) return { status: 200, body: preview }
        if (url.endsWith('/audience'))
          return {
            status: 409,
            body: {
              error: {
                code: 'conflict',
                message: 'La collection a changé. Vérifiez le nouvel aperçu.',
              },
            },
          }
        return response(url)
      },
    )
    fireEvent.change(await screen.findByLabelText('Visibilité'), { target: { value: 'friends' } })
    await screen.findByText('Disque approuvé')
    fireEvent.click(screen.getByRole('button', { name: 'Partager avec mes amis' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('La collection a changé.')
    expect(screen.queryByText('Collection partagée avec succès.')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Partager avec mes amis' })).toBeEnabled()
  })
  it('shows allowed community collections with a reachable shared view', async () => {
    mount(<CommunityCollectionsPage />, '/', '/', (url) =>
      url.includes('/collection-publications')
        ? {
            status: 200,
            body: {
              collections: [
                {
                  id: 'shared',
                  name: 'Livres de Noa',
                  owner: 'noa',
                  cover: '',
                  visibility: 'followers',
                  items: [],
                },
              ],
              page: 1,
              hasMore: false,
            },
          }
        : response(url),
    )
    expect(await screen.findByRole('link', { name: /Livres de Noa/ })).toHaveAttribute(
      'href',
      '/communaute/collections/shared',
    )
  })
  it('shows a denied response without exposing the private inventory', async () => {
    mount(
      <CommunityCollectionsPage />,
      '/communaute/collections/denied',
      '/communaute/collections/:collectionId',
      (url) =>
        url.includes('/collection-publications/')
          ? { status: 404, body: { error: { message: 'Collection indisponible' } } }
          : response(url),
    )
    expect(await screen.findByRole('alert')).toHaveTextContent('Collection indisponible')
    expect(screen.queryByText('Pull noir')).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /Modifier/ })).not.toBeInTheDocument()
  })
})
