import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './auth/AuthContext.tsx'
import { ProtectedRoute } from './auth/ProtectedRoute.tsx'
import Home from './pages/Home.tsx'
import LoginPage from './pages/LoginPage.tsx'
import ProfilesPage from './pages/ProfilesPage.tsx'
import RegisterPage from './pages/RegisterPage.tsx'

function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/inscription" element={<RegisterPage />} />
          <Route path="/connexion" element={<LoginPage />} />
          <Route
            path="/profils"
            element={
              <ProtectedRoute>
                <ProfilesPage />
              </ProtectedRoute>
            }
          />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  )
}

export default App
