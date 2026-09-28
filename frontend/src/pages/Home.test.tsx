import { cleanup, render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import Home from './Home.tsx'

describe('Home', () => {
  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
  })

  it('renders the Penderie landing page', () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ status: 'ok', environment: 'test' }),
      }),
    )

    render(<Home />)

    expect(
      screen.getByText('Votre dressing, dans votre téléphone.', { exact: false }),
    ).toBeInTheDocument()
    expect(screen.getByText('Qu’est-ce que je possède ?')).toBeInTheDocument()
  })

  it('shows the API as online when the health endpoint answers', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ status: 'ok', environment: 'test' }),
      }),
    )

    render(<Home />)

    await waitFor(() => {
      expect(screen.getByTestId('api-badge')).toHaveTextContent('API en ligne (test)')
    })
  })

  it('shows the API as offline when the health endpoint fails', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network down')))

    render(<Home />)

    await waitFor(() => {
      expect(screen.getByTestId('api-badge')).toHaveTextContent('API hors ligne')
    })
  })
})
