import { useContext } from 'react'
import { AuthContext } from '../auth/AuthContext.tsx'
import { Link, useLocation } from 'react-router-dom'
import { House, MapPin, ScanLine, Shirt, UserRound } from 'lucide-react'
import { t } from '../i18n/copy.ts'

export default function AppNav() {
  const { pathname } = useLocation()
  const auth = useContext(AuthContext)
  const child = auth?.activeProfile?.role === 'child'
  const tabs = [
    {
      to: '/actualites',
      label: t('nav.feed'),
      Icon: House,
      active: pathname === '/actualites' || pathname.startsWith('/communaute'),
    },
    {
      to: '/dressing',
      label: t('nav.wardrobe'),
      Icon: Shirt,
      active: pathname === '/dressing' || pathname.startsWith('/articles'),
    },
    {
      to: '/scanner',
      label: t('nav.scan'),
      Icon: ScanLine,
      active: pathname === '/scanner',
    },
    {
      to: '/foyer',
      label: t('nav.home'),
      Icon: MapPin,
      active: pathname.startsWith('/foyer') || pathname === '/lieux',
    },
    {
      to: '/profil',
      label: t('nav.profile'),
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
        '/my-space',
      ].some((path) => pathname === path || pathname.startsWith(path + '/')),
    },
  ]
  return (
    <nav className="app-nav" aria-label={t('nav.main')}>
      {(child
        ? [
            tabs[1],
            tabs[2],
            {
              to: '/lieux',
              label: t('nav.rooms'),
              Icon: MapPin,
              active: pathname === '/lieux',
            },
            {
              to: '/notifications',
              label: t('nav.notifications'),
              Icon: House,
              active: pathname === '/notifications',
            },
            {
              to: '/enfant',
              label: t('nav.mySpace'),
              Icon: UserRound,
              active: pathname === '/enfant' || pathname.startsWith('/enfant/'),
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
