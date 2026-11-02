import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    host: true,
    port: 5173,
    strictPort: true,
    // Let the Cypress e2e container reach the dev server through the
    // Docker Compose service name (Vite's default host check would 403 it).
    allowedHosts: ['frontend'],
    proxy: {
      '/api': {
        target: process.env.API_PROXY_TARGET ?? 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: false,
    // This dev VM has ~4GB RAM; more forks than this starves worker startup.
    maxWorkers: 4,
    // Cold transform caches on this machine make 5s flaky; 15s keeps `make qa` deterministic.
    testTimeout: 15000,
    hookTimeout: 15000,
  },
})
