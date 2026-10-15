const { defineConfig } = require('cypress')

module.exports = defineConfig({
  e2e: {
    // From inside the cypress container (Docker Compose network),
    // the Vite dev server is reachable through its service name.
    baseUrl: 'http://frontend:5173',
    viewportWidth: 1280,
    viewportHeight: 800,
    video: true,
    screenshotOnRunFailure: true,
    // Auth calls (register + SMTP e-mail, login + /me + redirect) can take
    // several seconds against the dev stack — give assertions room to retry.
    defaultCommandTimeout: 10000,
    requestTimeout: 15000,
  },
})
