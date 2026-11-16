import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import MySpacePage from './MySpacePage.tsx'
import type { MySpaceData } from '../api/mySpace.ts'
import { stubFetch } from '../test/http.ts'

const ADULT_SPACE: MySpaceData = {
  household: { id: 1, name: 'Famille Moreau' },
  isChild: false,
  canManage: true,
  stats: { items: 1, places: 1, profiles: 2, activeLoans: 1 },
  items: [
    {
      id: 7,
      name: 'Veste noire',
      status: 'disponible',
      photoUrl: '/api/items/7/photos/2',
      place: 'Maison · Dressing',
      roomId: 3,
      ownerProfileId: 1,
    },
  ],
  places: [{ id: 1, name: 'Maison', rooms: [{ id: 3, name: 'Dressing', itemCount: 1 }] }],
  profiles: [
    { id: 1, name: 'Camille', role: 'parent', itemCount: 1 },
    { id: 2, name: 'Léo', role: 'child', itemCount: 0 },
  ],
  members: [{ id: 'user-1', name: 'Camille', role: 'parent', lastLoginAt: null }],
  loans: [
    {
      id: 9,
      itemName: 'Veste noire',
      borrower: 'alex_moreau',
      since: '2026-11-14T10:00:00+00:00',
      canReturn: true,
    },
  ],
  shares: [{ id: 4, itemName: 'Veste noire', expiresAt: '2026-11-20T10:00:00+00:00' }],
  activity: [
    {
      id: 11,
      action: 'item.updated',
      actor: 'Camille',
      objectName: 'Veste noire',
      createdAt: '2026-11-15T10:30:00+00:00',
      changes: ['color', 'size'],
      itemId: 7,
      canEdit: true,
    },
  ],
}

function renderPage() {
  render(
    <MemoryRouter>
      <MySpacePage />
    </MemoryRouter>,
  )
}

describe('MySpacePage', () => {
  afterEach(() => cleanup())

  it('combines profile and sign-in-account counts in one household detail', async () => {
    stubFetch((url) =>
      url.endsWith('/api/me/space')
        ? { status: 200, body: ADULT_SPACE }
        : { status: 404, body: {} },
    )
    renderPage()

    expect(await screen.findByText('Mon activité récente')).toBeInTheDocument()
    expect(
      screen.getAllByRole('button').filter((button) => button.classList.contains('my-space-stat')),
    ).toHaveLength(4)
    fireEvent.click(screen.getByRole('button', { name: /Foyer/ }))

    expect(await screen.findByText('Profils du foyer')).toBeInTheDocument()
    expect(screen.getByText('Léo')).toBeInTheDocument()
    expect(screen.getByText('Comptes pouvant se connecter')).toBeInTheDocument()
    expect(screen.getByText(/Aucune connexion enregistrée/)).toBeInTheDocument()
    expect(screen.getByText('Camille a modifié « Veste noire »')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Camille a modifié « Veste noire »' })).toHaveAttribute(
      'href',
      '/articles/7/modifier',
    )
    expect(screen.getByText('Détails modifiés : couleur · taille')).toBeInTheDocument()
    expect(screen.queryByText('/api/items/7/photos/2')).not.toBeInTheDocument()
    expect(screen.queryByText('Actions (30 j)')).not.toBeInTheDocument()
  })

  it('lets an adult return a loan and revoke an active share', async () => {
    let returned = false
    let revoked = false
    stubFetch((url, init) => {
      if (url.endsWith('/api/me/space')) {
        return {
          status: 200,
          body: {
            ...ADULT_SPACE,
            loans: returned ? [] : ADULT_SPACE.loans,
            shares: revoked ? [] : ADULT_SPACE.shares,
          },
        }
      }
      if (url.endsWith('/api/social/loans/9/return')) {
        returned = true
        return { status: 200, body: {} }
      }
      if (url.endsWith('/api/social/shares/4') && init?.method === 'DELETE') {
        revoked = true
        return { status: 204, body: null }
      }
      return { status: 404, body: {} }
    })
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: /Prêts en cours/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Marquer comme rendu' }))
    expect(
      await screen.findByText('Aucun prêt en cours. Vos objets prêtés apparaîtront ici.'),
    ).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Fermer le détail' }))
    fireEvent.click(screen.getByRole('button', { name: 'Révoquer le lien' }))
    expect(
      await screen.findByText('Aucun partage actif. Vous pourrez les gérer ici.'),
    ).toBeInTheDocument()
    expect(returned).toBe(true)
    expect(revoked).toBe(true)
  })

  it('lets a user dismiss a readable activity from their own feed', async () => {
    let dismissed = false
    stubFetch((url, init) => {
      if (url.endsWith('/api/me/space')) {
        return {
          status: 200,
          body: { ...ADULT_SPACE, activity: dismissed ? [] : ADULT_SPACE.activity },
        }
      }
      if (url.endsWith('/api/me/space/activity/11') && init?.method === 'DELETE') {
        dismissed = true
        return { status: 204, body: null }
      }
      return { status: 404, body: {} }
    })
    renderPage()

    fireEvent.click(
      await screen.findByRole('button', {
        name: 'Retirer de mon activité : Camille a modifié « Veste noire »',
      }),
    )

    expect(
      await screen.findByText('Les changements importants de votre foyer apparaîtront ici.'),
    ).toBeInTheDocument()
    expect(dismissed).toBe(true)
  })

  it('shows item and room details from their tappable cards', async () => {
    stubFetch((url) =>
      url.endsWith('/api/me/space')
        ? { status: 200, body: ADULT_SPACE }
        : { status: 404, body: {} },
    )
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: /Objets/ }))
    expect(screen.getByRole('img', { name: 'Veste noire' })).toBeInTheDocument()
    expect(screen.getByText(/Maison · Dressing/)).toBeInTheDocument()
    expect(screen.getByText(/Disponible/)).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Fermer le détail' }))
    fireEvent.click(screen.getByRole('button', { name: /Lieux/ }))
    expect(await screen.findByText('Dressing')).toBeInTheDocument()
    expect(screen.getByText('1 objet')).toBeInTheDocument()
  })

  it('limits a child dashboard to their own items and loans', async () => {
    const childSpace: MySpaceData = {
      ...ADULT_SPACE,
      isChild: true,
      canManage: false,
      stats: { items: 1, places: 0, profiles: 0, activeLoans: 0 },
      items: [{ ...ADULT_SPACE.items[0], place: '' }],
      profiles: [],
      members: [],
      shares: [],
      loans: [],
      activity: [],
    }
    stubFetch((url) =>
      url.endsWith('/api/me/space') ? { status: 200, body: childSpace } : { status: 404, body: {} },
    )
    renderPage()

    expect(await screen.findByText('Objets à compléter')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Compléter' })).toHaveClass('my-space-incomplete__open')
    expect(screen.getAllByRole('button')).toHaveLength(2)
    expect(screen.queryByText('À vendre / peu utilisés')).not.toBeInTheDocument()
    expect(screen.queryByText('Partages actifs')).not.toBeInTheDocument()
    expect(screen.queryByText('Comptes pouvant se connecter')).not.toBeInTheDocument()
  })
})
