import { useContext } from 'react'
import { AuthContext } from '../auth/AuthContext.tsx'
import { Link, useLocation } from 'react-router-dom'
import { House, MapPin, ScanLine, Shirt, UserRound } from 'lucide-react'
import { getCopy as t } from '../lib/itemCatalog.ts'

export default function AppNav() {
  const { pathname } = useLocation()
  const auth = useContext(AuthContext)
  const child = auth?.activeProfile?.role === 'child'
  const tabs = [
    {
      to: '/actualites',
      label: t('Fil', 'Feed'),
      Icon: House,
      active: pathname === '/actualites' || pathname.startsWith('/communaute'),
    },
    {
      to: '/dressing',
      label: t('Dressing', 'Wardrobe'),
      Icon: Shirt,
      active: pathname === '/dressing' || pathname.startsWith('/articles'),
    },
    {
      to: '/scanner',
      label: t('Scanner', 'Scan'),
      Icon: ScanLine,
      active: pathname === '/scanner',
    },
    {
      to: '/foyer',
      label: t('Foyer', 'Home'),
      Icon: MapPin,
      active: pathname.startsWith('/foyer') || pathname === '/lieux',
    },
    {
      to: '/profil',
      label: t('Profil', 'Profile'),
      Icon: UserRound,
      active: [
        '/profil',
        '/collections',
        '/amis',
        '/profils',
        '/compte',
        '/vitrine',
        '/prets',
        '/acces',
      ].some((path) => pathname === path || pathname.startsWith(path + '/')),
    },
  ]
  return (
    <nav className="app-nav" aria-label={t('Navigation principale', 'Main navigation')}>
      {(child
        ? [
            tabs[1],
            tabs[2],
            {
              to: '/lieux',
              label: t('Pièces', 'Rooms'),
              Icon: MapPin,
              active: pathname === '/lieux',
            },
            {
              to: '/notifications',
              label: t('Notifications', 'Notifications'),
              Icon: House,
              active: pathname === '/notifications',
            },
            {
              to: '/enfant',
              label: t('Mon espace', 'My space'),
              Icon: UserRound,
              active: pathname.startsWith('/enfant'),
            },
          ]
        : tabs
      ).map(({ to, label, Icon, active }) => (
        <Link
          key={to}
          to={to}
          aria-current={active ? 'page' : undefined}
          className={`app-tab${active ? ' app-tab--active' : ''}${to === '/scanner' ? ' app-tab--scan' : ''}`}
        >
          <Icon size={22} aria-hidden="true" />
          <span>{label}</span>
        </Link>
      ))}
    </nav>
  )
}
