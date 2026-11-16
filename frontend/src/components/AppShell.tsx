import { t } from '../i18n/copy.ts'
import { Link, Outlet } from 'react-router-dom'
import AppNav from './AppNav.tsx'
import LanguageSwitcher from './LanguageSwitcher.tsx'

/**
 * Authenticated application shell: side/bottom navigation + current page.
 * Every tab page renders inside <main> through the router outlet.
 */
export default function AppShell() {
  return (
    <div className="app-shell">
      <span className="auth-brand app-brand">Penderie</span>
      <LanguageSwitcher compact className="app-language" />
      <Link className="app-notifications-link" to="/notifications">
        {t('nav.notifications')}
      </Link>
      <main className="app-main">
        <Outlet />
      </main>
      <AppNav />
    </div>
  )
}
