import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from '../auth/AuthContext.tsx'
import { ProtectedRoute } from '../auth/ProtectedRoute.tsx'
import ItemsPage from '../pages/ItemsPage.tsx'
import LoginPage from '../pages/LoginPage.tsx'
import ScanPage from '../pages/ScanPage.tsx'
import { jsonBody, stubFetch, unauthorized } from '../test/http.ts'

vi.mock('../lib/image.ts', () => ({
  resizeToBase64: vi.fn(async () => 'data:image/jpeg;base64,preview'),
}))

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

const SCAN_RESULT = {
  name: 'Pull noir',
  type: 'pull',
  category: 'Pulls',
  brand: 'Zara',
  color: 'noir',
  size: 'M',
  material: 'laine',
  condition: 'tres_bon',
  confidence: 0.85,
}

function renderScanner(handler: (url: string, init?: RequestInit) => { status: number; body: unknown }) {
  stubFetch(handler)
  render(
    <MemoryRouter initialEntries={['/scanner']}>
      <AuthProvider>
        <Routes>
          <Route
            path="/scanner"
            element={
              <ProtectedRoute>
                <ScanPage />
              </ProtectedRoute>
            }
          />
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

describe('ScanPage', () => {
  beforeEach(() => {
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: undefined,
    })
  })

  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('scans a photo then saves the pre-filled item', async () => {
    let saved: Record<string, unknown> = {}
    renderScanner((url, init) => {
      if (url.endsWith('/api/auth/me')) return ME
      if (url.endsWith('/api/scan')) return { status: 200, body: SCAN_RESULT }
      if (url.endsWith('/api/items') && init?.method === 'POST') {
        saved = init === undefined ? {} : jsonBody(init)
        return { status: 201, body: { item: { id: 7, name: 'Pull noir' } } }
      }
      if (url.includes('/api/items')) {
        return { status: 200, body: { items: [], total: 0, page: 1, perPage: 20 } }
      }
      return { status: 404, body: {} }
    })

    const input = await screen.findByLabelText('Choisir depuis la galerie')
    fireEvent.change(input, {
      target: { files: [new File(['photo'], 'pull.jpg', { type: 'image/jpeg' })] },
    })

    expect(await screen.findByText('Vérifier et compléter')).toBeInTheDocument()
    expect(screen.getByText('Zara')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Vérifier et compléter' }))
    expect(await screen.findByLabelText("Nom de l'article")).toBeInTheDocument()

    const form = screen.getByLabelText("Nom de l'article").closest('form')
    if (form === null) throw new Error('Item form not found')
    fireEvent.submit(form)

    await waitFor(() => {
      expect(screen.getByText(/dressing est encore vide/)).toBeInTheDocument()
    })
    expect(saved['name']).toBe('Pull noir')
    expect(saved['color']).toBe('noir')
  })

  it('shows a clear error when the photo cannot be analyzed', async () => {
    renderScanner((url) => {
      if (url.endsWith('/api/auth/me')) return ME
      if (url.endsWith('/api/scan')) {
        return {
          status: 422,
          body: { error: { code: 'validation_failed', message: 'Photo illisible.' } },
        }
      }
      return { status: 404, body: {} }
    })

    const input = await screen.findByLabelText('Choisir depuis la galerie')
    fireEvent.change(input, {
      target: { files: [new File(['photo'], 'pull.jpg', { type: 'image/jpeg' })] },
    })

    expect(await screen.findByText('Photo illisible.')).toBeInTheDocument()
  })

  it('redirects anonymous users to the login page', async () => {
    renderScanner(() => unauthorized())

    expect(await screen.findByLabelText('E-mail')).toBeInTheDocument()
  })

  it('lets the user pick a garment guide and sends it as a scan hint', async () => {
    let scanned: Record<string, unknown> = {}
    renderScanner((url, init) => {
      if (url.endsWith('/api/auth/me')) return ME
      if (url.endsWith('/api/scan')) {
        scanned = init === undefined ? {} : jsonBody(init)
        return { status: 200, body: SCAN_RESULT }
      }
      if (url.includes('/api/items')) {
        return { status: 200, body: { items: [], total: 0, page: 1, perPage: 20 } }
      }
      return { status: 404, body: {} }
    })

    expect(await screen.findByRole('radiogroup', { name: 'Type d’article' })).toBeInTheDocument()
    fireEvent.click(screen.getByRole('radio', { name: 'Jupe' }))

    const input = screen.getByLabelText('Choisir depuis la galerie')
    fireEvent.change(input, {
      target: { files: [new File(['photo'], 'jupe.jpg', { type: 'image/jpeg' })] },
    })

    expect(await screen.findByText('Vérifier et compléter')).toBeInTheDocument()
    expect(scanned['typeHint']).toBe('jupe')
  })

  it('opens the live camera and captures a photo from its preview', async () => {
    const stop = vi.fn()
    const stream = { getTracks: () => [{ stop }] } as unknown as MediaStream
    const getUserMedia = vi.fn(async () => stream)
    vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue()
    Object.defineProperty(HTMLVideoElement.prototype, 'videoWidth', { configurable: true, value: 640 })
    Object.defineProperty(HTMLVideoElement.prototype, 'videoHeight', { configurable: true, value: 480 })
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({
      drawImage: vi.fn(),
    } as unknown as CanvasRenderingContext2D)
    vi.spyOn(HTMLCanvasElement.prototype, 'toDataURL').mockReturnValue(
      'data:image/jpeg;base64,captured',
    )
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: { getUserMedia },
    })
    let scanned: Record<string, unknown> = {}
    renderScanner((url, init) => {
      if (url.endsWith('/api/auth/me')) return ME
      if (url.endsWith('/api/scan')) {
        scanned = jsonBody(init)
        return { status: 200, body: SCAN_RESULT }
      }
      if (url.includes('/api/items')) {
        return { status: 200, body: { items: [], total: 0, page: 1, perPage: 20 } }
      }
      return { status: 404, body: {} }
    })

    expect(await screen.findByLabelText('Aperçu de la caméra')).toBeInTheDocument()
    expect(getUserMedia).toHaveBeenCalledWith({
      audio: false,
      video: { facingMode: { ideal: 'environment' } },
    })

    fireEvent.click(screen.getByRole('button', { name: 'Prendre la photo' }))
    expect(await screen.findByText('Vérifier et compléter')).toBeInTheDocument()
    expect(scanned['image']).toBe('data:image/jpeg;base64,captured')
    await waitFor(() => expect(stop).toHaveBeenCalled())
  })

  it('keeps gallery selection available while the live camera is active', async () => {
    const stop = vi.fn()
    const stream = { getTracks: () => [{ stop }] } as unknown as MediaStream
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: { getUserMedia: vi.fn(async () => stream) },
    })
    vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue()

    renderScanner((url) => {
      if (url.endsWith('/api/auth/me')) return ME
      if (url.endsWith('/api/scan')) return { status: 200, body: SCAN_RESULT }
      return { status: 404, body: {} }
    })

    expect(await screen.findByLabelText('Aperçu de la caméra')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: /Choisir depuis la galerie/ }))
    fireEvent.change(screen.getByLabelText('Choisir depuis la galerie'), {
      target: { files: [new File(['photo'], 'pull.jpg', { type: 'image/jpeg' })] },
    })

    expect(await screen.findByText('Vérifier et compléter')).toBeInTheDocument()
    await waitFor(() => expect(stop).toHaveBeenCalled())
  })

  it('keeps the captured photo and allows manual entry when AI is rate limited', async () => {
    let saved: Record<string, unknown> = {}
    renderScanner((url, init) => {
      if (url.endsWith('/api/auth/me')) return ME
      if (url.endsWith('/api/scan')) {
        return {
          status: 503,
          body: {
            error: {
              code: 'service_unavailable',
              message: 'Photo analysis is temporarily rate-limited.',
            },
          },
        }
      }
      if (url.endsWith('/api/items') && init?.method === 'POST') {
        saved = jsonBody(init)
        return { status: 201, body: { item: { id: 8, name: 'Jupe bleue' } } }
      }
      if (url.includes('/api/items')) {
        return { status: 200, body: { items: [], total: 0, page: 1, perPage: 20 } }
      }
      return { status: 404, body: {} }
    })

    fireEvent.click(await screen.findByRole('radio', { name: 'Jupe' }))
    fireEvent.change(await screen.findByLabelText('Choisir depuis la galerie'), {
      target: { files: [new File(['photo'], 'jupe.jpg', { type: 'image/jpeg' })] },
    })

    expect(await screen.findByText(/L’analyse photo gratuite est temporairement indisponible/)).toBeInTheDocument()
    expect(screen.getByAltText('Photo conservée pour la saisie manuelle')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Saisir l’article manuellement' }))

    const nameInput = await screen.findByLabelText("Nom de l'article")
    expect(screen.getByRole('combobox', { name: 'Type' })).toHaveValue('jupe')
    fireEvent.change(nameInput, { target: { value: 'Jupe bleue' } })

    const form = nameInput.closest('form')
    if (form === null) throw new Error('Item form not found')
    fireEvent.submit(form)

    await waitFor(() => expect(saved['name']).toBe('Jupe bleue'))
    expect(saved['photos']).toEqual(['data:image/jpeg;base64,preview'])
  })
})
