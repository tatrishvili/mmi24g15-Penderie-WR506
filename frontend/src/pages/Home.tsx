import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'

type HealthState =
  { status: 'loading' } | { status: 'online'; environment: string } | { status: 'offline' }

const PROMISES = [
  {
    title: 'Qu’est-ce que je possède ?',
    text: 'Retrouvez tous vos vêtements et objets en un seul endroit.',
  },
  {
    title: 'Où est-ce ?',
    text: 'Chaque objet a sa pièce, son armoire, son carton, sa place exacte.',
  },
  {
    title: 'À qui l’ai-je prêté ?',
    text: 'Gardez la trace de vos prêts : qui, quoi, depuis quand.',
  },
  {
    title: 'Qu’est-ce que j’en fais ?',
    text: 'Porter, conserver, prêter, donner ou vendre en confiance.',
  },
]

export default function Home() {
  const [health, setHealth] = useState<HealthState>({ status: 'loading' })

  useEffect(() => {
    let cancelled = false

    fetch('/api/health')
      .then((response) =>
        response.ok ? response.json() : Promise.reject(new Error(`HTTP ${response.status}`)),
      )
      .then((data: { status?: string; environment?: string }) => {
        if (!cancelled && data.status === 'ok') {
          setHealth({ status: 'online', environment: data.environment ?? 'unknown' })
        } else if (!cancelled) {
          setHealth({ status: 'offline' })
        }
      })
      .catch(() => {
        if (!cancelled) {
          setHealth({ status: 'offline' })
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  const badge =
    health.status === 'loading'
      ? { label: 'API : connexion…', className: 'badge badge--pending' }
      : health.status === 'online'
        ? { label: `API en ligne (${health.environment})`, className: 'badge badge--online' }
        : { label: 'API hors ligne', className: 'badge badge--offline' }

  return (
    <div className="page">
      <header className="header">
        <span className="brand">Penderie</span>
        <span data-testid="api-badge" className={badge.className}>
          {badge.label}
        </span>
      </header>

      <main className="main">
        <section className="hero">
          <h1>Votre dressing, dans votre téléphone.</h1>
          <p>
            Scannez un vêtement, Penderie l’enregistre, le range et vous dit exactement où il est —
            et à qui vous l’avez prêté.
          </p>
          <div style={{ display: 'flex', gap: 12, marginTop: 20, flexWrap: 'wrap' }}>
            <Link
              to="/inscription"
              className="btn btn-primary"
              style={{ width: 'auto', flex: '1 1 200px' }}
            >
              Créer mon foyer
            </Link>
            <Link
              to="/connexion"
              className="btn btn-secondary"
              style={{ width: 'auto', flex: '1 1 200px' }}
            >
              Se connecter
            </Link>
          </div>
        </section>

        <section className="grid">
          {PROMISES.map((promise) => (
            <article key={promise.title} className="card">
              <h2>{promise.title}</h2>
              <p>{promise.text}</p>
            </article>
          ))}
        </section>
      </main>

      <footer className="footer">Environnement de développement — étape Setup</footer>
    </div>
  )
}
