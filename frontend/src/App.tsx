import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './auth/AuthContext.tsx'
import { ProtectedRoute } from './auth/ProtectedRoute.tsx'
import SocialFeedPage from './pages/SocialFeedPage.tsx'
import CommunityCollectionsPage from './pages/CommunityCollectionsPage.tsx'
import AppShell from './components/AppShell.tsx'
import AccountPage from './pages/AccountPage.tsx'
import FriendsPage from './pages/FriendsPage.tsx'
import Home from './pages/Home.tsx'
import ItemsPage from './pages/ItemsPage.tsx'
import OnboardingPage from './pages/OnboardingPage.tsx'
import PlacesPage from './pages/PlacesPage.tsx'
import LoginPage from './pages/LoginPage.tsx'
import ProfilesPage from './pages/ProfilesPage.tsx'
import RegisterPage from './pages/RegisterPage.tsx'
import ScanPage from './pages/ScanPage.tsx'
import HouseholdSetupPage from './pages/HouseholdSetupPage.tsx'
import SocialProfilePage from './pages/SocialProfilePage.tsx'
import ItemDetailPage, { AccessPage } from './pages/ItemDetailPage.tsx'
import ItemEditorPage from './pages/ItemEditorPage.tsx'
import LoansPage, { SharedItemPage } from './pages/LoansPage.tsx'
import VerifyEmailPage from './pages/VerifyEmailPage.tsx'

function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/onboarding/:step" element={<OnboardingPage />} />
          <Route path="/inscription" element={<RegisterPage />} />
          <Route path="/verifier-email" element={<VerifyEmailPage />} />
          <Route path="/partage/:token" element={<SharedItemPage />} />
          <Route path="/connexion" element={<LoginPage />} />
          <Route
            element={
              <ProtectedRoute>
                <AppShell />
              </ProtectedRoute>
            }
          >
            <Route path="/foyer" element={<HouseholdSetupPage key="overview" step="overview" />} />
            <Route
              path="/foyer/creer"
              element={<HouseholdSetupPage key="create" step="create" />}
            />
            <Route
              path="/foyer/membres"
              element={<HouseholdSetupPage key="members" step="members" />}
            />
            <Route path="/foyer/enfant" element={<HouseholdSetupPage key="child" step="child" />} />
            <Route
              path="/foyer/pieces/ajouter"
              element={<HouseholdSetupPage key="rooms" step="rooms" />}
            />
            <Route
              path="/foyer/rangements"
              element={<HouseholdSetupPage key="storage" step="storage" />}
            />
            <Route
              path="/foyer/rangement/ajouter"
              element={<HouseholdSetupPage key="add-storage" step="add-storage" />}
            />
            <Route path="/profil" element={<SocialProfilePage key="profile" view="profile" />} />
            <Route path="/profil/modifier" element={<SocialProfilePage key="edit" view="edit" />} />
            <Route
              path="/collections"
              element={<SocialProfilePage key="collections" view="collections" />}
            />
            <Route
              path="/collections/nouvelle"
              element={<SocialProfilePage key="create" view="create" />}
            />
            <Route
              path="/collections/:collectionId"
              element={<SocialProfilePage key="collection" view="collection" />}
            />
            <Route path="/actualites" element={<SocialFeedPage />} />
            <Route path="/communaute/profils/:username" element={<SocialFeedPage />} />
            <Route
              path="/communaute/collections"
              element={<CommunityCollectionsPage key="discover" />}
            />
            <Route
              path="/communaute/collections/:collectionId"
              element={<CommunityCollectionsPage key="published" />}
            />
            <Route path="/vitrine" element={<SocialProfilePage key="showcase" view="showcase" />} />
            <Route path="/articles/nouveau" element={<ItemEditorPage />} />
            <Route path="/articles/:id" element={<ItemDetailPage key="detail" view="detail" />} />
            <Route path="/articles/:id/modifier" element={<ItemEditorPage />} />
            <Route
              path="/articles/:id/emplacement"
              element={<ItemEditorPage key="location" locationOnly />}
            />
            <Route
              path="/articles/:id/actions"
              element={<ItemDetailPage key="actions" view="actions" />}
            />
            <Route
              path="/articles/:id/visibilite"
              element={<ItemDetailPage key="visibility" view="visibility" />}
            />
            <Route
              path="/articles/:id/partager"
              element={<ItemDetailPage key="share" view="share" />}
            />
            <Route
              path="/articles/:id/archiver"
              element={<ItemDetailPage key="archive" view="archive" />}
            />
            <Route path="/acces" element={<AccessPage />} />
            <Route path="/emprunter/:token" element={<LoansPage key="request" view="request" />} />
            <Route path="/prets" element={<LoansPage key="list" view="list" />} />
            <Route
              path="/prets/:id/demande"
              element={<LoansPage key="received" view="received" />}
            />
            <Route path="/prets/:id" element={<LoansPage key="detail" view="detail" />} />
            <Route path="/compte" element={<AccountPage />} />
            <Route path="/scanner" element={<ScanPage />} />
            <Route path="/dressing" element={<ItemsPage />} />
            <Route path="/lieux" element={<PlacesPage />} />
            <Route path="/profils" element={<ProfilesPage />} />
            <Route path="/amis" element={<FriendsPage />} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  )
}

export default App
