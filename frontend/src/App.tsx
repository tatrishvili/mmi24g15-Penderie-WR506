import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './auth/AuthContext.tsx'
import { ProtectedRoute } from './auth/ProtectedRoute.tsx'
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
          <Route path="/connexion" element={<LoginPage />} />
          <Route
            element={
              <ProtectedRoute>
                <AppShell />
              </ProtectedRoute>
            }
          >
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
