import { Outlet } from 'react-router-dom'
import AppNav from './AppNav.tsx'

/**
 * Authenticated application shell: side/bottom navigation + current page.
 * Every tab page renders inside <main> through the router outlet.
 */
export default function AppShell() {
  return (
    <div className="app-shell">
      <span className="auth-brand app-brand">Penderie</span>
      <main className="app-main">
        <Outlet />
      </main>
      <AppNav />
    </div>
  )
}
