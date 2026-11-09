import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { I18nextProvider } from 'react-i18next'
import '@fontsource/baloo-chettan-2/400.css'
import '@fontsource/baloo-chettan-2/500.css'
import '@fontsource/baloo-chettan-2/600.css'
import '@fontsource/baloo-chettan-2/700.css'
import '@fontsource/noto-sans-georgian/400.css'
import '@fontsource/noto-sans-georgian/500.css'
import '@fontsource/noto-sans-georgian/600.css'
import '@fontsource/noto-sans-georgian/700.css'
import './index.css'
import i18n from './i18n/config.ts'
import App from './App.tsx'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <I18nextProvider i18n={i18n}>
      <App />
    </I18nextProvider>
  </StrictMode>,
)
