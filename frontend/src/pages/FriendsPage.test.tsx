import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import { AuthProvider } from '../auth/AuthContext.tsx'
import FriendsPage from './FriendsPage.tsx'
import type { FriendsResult } from '../types/friends.ts'
import { jsonBody, stubFetch, unauthorized } from '../test/http.ts'

const ME = {
  status: 200,
  body: {
    households: [],
    household: null,
    profiles: [],
    places: [],
    activeHouseholdId: null,
    activeProfileId: null,
    activeProfile: null,
    user: { id: 1, email: 'camille@example.com', username: 'camille_test' },
  },
}

function renderPage(path = '/amis') {
  render(
    <MemoryRouter initialEntries={[path]}>
      <AuthProvider>
        <FriendsPage />
      </AuthProvider>
    </MemoryRouter>,
  )
}

describe('FriendsPage', () => {
  afterEach(() => {
    cleanup()
  })

  it('sends a request by username and accepts an incoming request', async () => {
    let result: FriendsResult = {
      friends: [],
      incoming: [{ id: 4, user: { id: 2, username: 'sophie_test', firstName: 'Sophie' } }],
      outgoing: [],
      followers: [],
      following: [],
    }
    let sentUsername = ''

    stubFetch((url, init) => {
      if (url.endsWith('/api/auth/me')) return ME
      if (url.endsWith('/api/friends') && init?.method !== 'POST') return { status: 200, body: result }
      if (url.endsWith('/api/friends/requests') && init?.method === 'POST') {
        sentUsername = String(jsonBody(init).username)
        result = {
          friends: [],
          incoming: result.incoming,
          outgoing: [{ id: 5, user: { id: 3, username: 'lea_test', firstName: 'Léa' } }],
          followers: result.followers,
          following: [{ id: 8, user: { id: 3, username: 'lea_test', firstName: 'Léa' } }],
        }
        return { status: 201, body: { request: result.outgoing[0] } }
      }
      if (url.endsWith('/api/friends/requests/4/accept')) {
        result = {
          friends: [{ id: 4, user: { id: 2, username: 'sophie_test', firstName: 'Sophie' } }],
          incoming: [],
          outgoing: result.outgoing,
          followers: result.followers,
          following: result.following,
        }
        return { status: 200, body: { friend: result.friends[0] } }
      }
      return unauthorized()
    })
    renderPage()

    expect(await screen.findByText(/Saisissez son nom d’utilisateur/i)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Sophie.*@sophie_test/ })).toHaveAttribute('href', '/communaute/profils/sophie_test')
    expect(screen.getByRole('link', { name: 'Voir le fil d’actualité' })).toHaveAttribute('href', '/actualites')
    fireEvent.change(await screen.findByLabelText('Nom d’utilisateur'), {
      target: { value: 'lea_test' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Envoyer la demande' }))

    expect(await screen.findByText('Demande envoyée.')).toBeInTheDocument()
    expect(sentUsername).toBe('lea_test')
    expect(await screen.findAllByText('@lea_test')).toHaveLength(2)

    fireEvent.click(screen.getByRole('button', { name: 'Accepter la demande' }))
    await waitFor(() => {
      expect(screen.getByText('Mes amis')).toBeInTheDocument()
      expect(screen.getByText('@sophie_test')).toBeInTheDocument()
    })
  })

  it('opens the requested connections list with the approved name, avatar and username', async () => {
    stubFetch((url) => url.endsWith('/api/auth/me') ? ME : { status: 200, body: { friends: [{ id: 2, user: { id: 2, username: 'camille_test', firstName: 'Moi', displayName: 'Camille Martin', photoUrl: '/avatar.png' } }], followers: [], following: [], incoming: [], outgoing: [] } })
    renderPage('/amis?liste=amis')
    const person = await screen.findByRole('link', { name: /Camille Martin.*@camille_test/ })
    expect(person).toHaveAttribute('href', '/communaute/profils/camille_test')
    expect(person.querySelector('img')).toHaveAttribute('src', '/avatar.png')
    expect(screen.queryByRole('heading', { name: 'Ajouter un ami' })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Demandes reçues' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Amis' })).toHaveAttribute('aria-current', 'page')
  })
  it('follows an account directly and shows it in following', async () => {
    let result: FriendsResult = {
      friends: [],
      incoming: [],
      outgoing: [],
      followers: [],
      following: [],
    }
    let followedUsername = ''

    stubFetch((url, init) => {
      if (url.endsWith('/api/auth/me')) return ME
      if (url.endsWith('/api/friends') && init?.method !== 'POST') return { status: 200, body: result }
      if (url.endsWith('/api/friends/following') && init?.method === 'POST') {
        followedUsername = String(jsonBody(init).username)
        result = {
          ...result,
          following: [{ id: 12, user: { id: 7, username: 'sophie_test', firstName: 'Sophie' } }],
        }
        return { status: 201, body: { follow: result.following[0] } }
      }
      return unauthorized()
    })
    renderPage()

    fireEvent.change(await screen.findByLabelText('Nom d’utilisateur'), {
      target: { value: 'sophie_test' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Suivre' }))

    expect(await screen.findByText('Vous suivez maintenant ce compte.')).toBeInTheDocument()
    expect(followedUsername).toBe('sophie_test')
    expect(await screen.findByText('@sophie_test')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: /Abonnements/ })).toBeInTheDocument()
  })

  it('shows an adult-account notice when the current session has no user account', async () => {
    stubFetch((url) => {
      if (url.endsWith('/api/auth/me')) {
        return {
          status: 200,
          body: {
            households: [],
            household: null,
            profiles: [],
            places: [],
            activeHouseholdId: null,
            activeProfileId: 2,
            activeProfile: { id: 2, name: 'Léo', role: 'child', avatarColor: null, hasPin: false, householdId: 1 },
          },
        }
      }
      return unauthorized()
    })
    renderPage()

    expect(await screen.findByText('Les demandes d’amitié sont réservées aux comptes adultes.')).toBeInTheDocument()
  })

  it('explains that the username must belong to a verified account when no match is found', async () => {
    stubFetch((url, init) => {
      if (url.endsWith('/api/auth/me')) return ME
      if (url.endsWith('/api/friends') && init?.method !== 'POST') {
        return { status: 200, body: { friends: [], incoming: [], outgoing: [], followers: [], following: [] } }
      }
      if (url.endsWith('/api/friends/requests') && init?.method === 'POST') {
        return {
          status: 404,
          body: { error: { code: 'not_found', message: 'No account was found for that username.' } },
        }
      }
      return unauthorized()
    })
    renderPage()

    fireEvent.change(await screen.findByLabelText('Nom d’utilisateur'), {
      target: { value: 'unknown_user' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Envoyer la demande' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Aucun compte vérifié trouvé avec ce nom d’utilisateur.',
    )
  })
})
