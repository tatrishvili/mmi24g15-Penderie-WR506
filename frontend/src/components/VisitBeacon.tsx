import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'

const STORAGE_KEY = 'penderie.visitor'

function visitorId(): string {
  try {
    const existing = window.localStorage.getItem(STORAGE_KEY)
    if (existing && /^[A-Za-z0-9_-]{8,64}$/.test(existing)) return existing
    const next =
      typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : Math.random().toString(36).slice(2).padEnd(16, '0')
    window.localStorage.setItem(STORAGE_KEY, next)
    return next
  } catch {
    return Math.random().toString(36).slice(2).padEnd(16, '0')
  }
}

export function VisitBeacon() {
  const location = useLocation()

  useEffect(() => {
    const body = JSON.stringify({
      path: location.pathname,
      visitorId: visitorId(),
      locale: (window.localStorage.getItem('penderie.locale') ?? 'fr').slice(0, 2),
    })

    try {
      void fetch('/api/analytics/visit', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body,
        keepalive: true,
      }).catch(() => undefined)
    } catch {
      // Analytics must never disturb navigation.
    }
  }, [location.pathname])

  return null
}
