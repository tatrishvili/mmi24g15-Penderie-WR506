import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { stubFetch } from '../test/http.ts'
import SocialFeedPage from './SocialFeedPage.tsx'

const record = (id: string, name = 'Mes vinyles') => ({
  id,
  name,
  cover: '',
  owner: 'camille',
  visibility: 'friends',
  items: [{ name: 'Un disque partagé', type: 'vinyle', color: 'Noir', photoUrl: null }],
})
function mount(path = '/actualites') {
  render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="/actualites" element={<SocialFeedPage />} />
        <Route path="/communaute/profils/:username" element={<SocialFeedPage />} />
      </Routes>
    </MemoryRouter>,
  )
}
afterEach(() => {
  cleanup()
  vi.unstubAllGlobals()
})

describe('Community feed and profiles', () => {
  it('shows real item previews and appends the next page without losing earlier cards', async () => {
    const urls: string[] = []
    stubFetch((url) => {
      urls.push(url)
      const secondPage = url.includes('page=2')
      return {
        status: 200,
        body: {
          collections: [
            record(secondPage ? 'two' : 'one', secondPage ? 'Mes livres' : 'Mes vinyles'),
          ],
          page: secondPage ? 2 : 1,
          hasMore: !secondPage,
        },
      }
    })
    mount()
    expect(await screen.findByRole('article', { name: 'Mes vinyles' })).toBeInTheDocument()
    expect(screen.getByText('Un disque partagé')).toBeInTheDocument()
    expect(urls[0]).toContain('scope=following')
    fireEvent.click(screen.getByRole('button', { name: 'Voir plus de partages' }))
    expect(await screen.findByRole('article', { name: 'Mes livres' })).toBeInTheDocument()
    expect(screen.getByRole('article', { name: 'Mes vinyles' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Voir plus de partages' })).not.toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: '@camille' })[0]).toHaveAttribute(
      'href',
      '/communaute/profils/camille',
    )
    expect(screen.getAllByRole('link', { name: 'Voir la collection' })[0]).toHaveAttribute(
      'href',
      '/communaute/collections/one',
    )
    fireEvent.click(screen.getByRole('button', { name: 'Découvrir' }))
    await waitFor(() => expect(urls.at(-1)).toContain('scope=community'))
    expect(screen.queryByRole('article', { name: 'Mes livres' })).not.toBeInTheDocument()
  })
  it('loads the next page automatically when scrolling reaches the sentinel', async () => {
    let intersection: IntersectionObserverCallback | undefined
    const disconnect = vi.fn()
    vi.stubGlobal(
      'IntersectionObserver',
      class {
        constructor(callback: IntersectionObserverCallback) {
          intersection = callback
        }
        observe() {}
        disconnect = disconnect
      },
    )
    stubFetch((url) => ({
      status: 200,
      body: {
        collections: [record(url.includes('page=2') ? 'two' : 'one')],
        hasMore: !url.includes('page=2'),
        page: url.includes('page=2') ? 2 : 1,
      },
    }))
    mount()
    await screen.findByRole('article')
    await waitFor(() => expect(intersection).toBeDefined())
    intersection!(
      [{ isIntersecting: true } as IntersectionObserverEntry],
      {} as IntersectionObserver,
    )
    await waitFor(() => expect(screen.getAllByRole('article')).toHaveLength(2))
    expect(disconnect).toHaveBeenCalled()
  })
  it('loads the selected profile and only requests that owners permitted collections', async () => {
    const urls: string[] = []
    stubFetch((url) => {
      urls.push(url)
      return url.includes('/profiles/')
        ? {
            status: 200,
            body: { profile: { username: 'camille', friend: true, following: true, self: false } },
          }
        : { status: 200, body: { collections: [record('one')], page: 1, hasMore: false } }
    })
    mount('/communaute/profils/camille')
    expect(await screen.findByRole('heading', { name: '@camille' })).toBeInTheDocument()
    expect(screen.getAllByText('Amis')[0]).toBeInTheDocument()
    expect(urls.some((url) => url.includes('owner=camille'))).toBe(true)
    expect(screen.queryByRole('button', { name: 'Découvrir' })).not.toBeInTheDocument()
  })
  it('revalidates visibility on focus and removes revoked collections', async () => {
    stubFetch((url) =>
      url.includes('/profiles/')
        ? {
            status: 200,
            body: {
              profile: {
                username: url.endsWith('camille') ? 'camille' : 'noa',
                friend: false,
                following: false,
                self: false,
              },
            },
          }
        : {
            status: 200,
            body: {
              collections: url.includes('owner=camille') ? [record('one')] : [],
              page: 1,
              hasMore: false,
            },
          },
    )
    mount('/communaute/profils/camille')
    expect(await screen.findByRole('article', { name: 'Mes vinyles' })).toBeInTheDocument()
    // Focus revalidates server permissions and discards any previously cached cards.
    stubFetch((url) =>
      url.includes('/profiles/')
        ? {
            status: 200,
            body: {
              profile: { username: 'camille', friend: false, following: false, self: false },
            },
          }
        : { status: 200, body: { collections: [], page: 1, hasMore: false } },
    )
    fireEvent.focus(window)
    expect(await screen.findByText('Pas encore de partage')).toBeInTheDocument()
    expect(screen.queryByRole('article', { name: 'Mes vinyles' })).not.toBeInTheDocument()
  })
  it('shows network errors and lets the reader retry', async () => {
    let failed = true
    stubFetch(() =>
      failed
        ? {
            status: 503,
            body: { error: { code: 'unavailable', message: 'Service indisponible.' } },
          }
        : { status: 200, body: { collections: [record('one')], page: 1, hasMore: false } },
    )
    mount()
    expect(await screen.findByRole('alert')).toHaveTextContent('Service indisponible.')
    failed = false
    fireEvent.click(screen.getByRole('button', { name: 'Réessayer' }))
    expect(await screen.findByRole('article', { name: 'Mes vinyles' })).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })
})
