import { NavLink } from 'react-router-dom'
import { Heart, MapPin, ScanLine, Shirt, UserRound, UsersRound } from 'lucide-react'
import { getCopy } from '../i18n/copy.ts'

const BASE_TABS = [
  { to: '/profil', label: 'Profil', Icon: UserRound },
  { to: '/scanner', label: 'Scanner', Icon: ScanLine },
  { to: '/dressing', label: 'Dressing', Icon: Shirt },
  { to: '/foyer', label: 'Foyer', Icon: MapPin },
  { to: '/profils', label: 'Profils', Icon: UsersRound },
] as const

export default function AppNav() {
  const copy = getCopy()
  const tabs = [
    ...BASE_TABS.slice(0, 4),
    { to: '/amis', label: copy.nav.friends, Icon: Heart },
    ...BASE_TABS.slice(4),
  ]

  return (
    <nav className="app-nav" aria-label="Navigation principale">
      {tabs.map(({ to, label, Icon }) => (
        <NavLink
          key={to}
          to={to}
          className={({ isActive }) => (isActive ? 'app-tab app-tab--active' : 'app-tab')}
        >
          <Icon size={22} aria-hidden="true" />
          <span>{label}</span>
        </NavLink>
      ))}
    </nav>
  )
}
