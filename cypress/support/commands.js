// Shared commands — every step is driven through the real UI of the SPA
// (the session cookie is the only auth mechanism; no localStorage).
const MAILPIT_URL = 'http://mailpit:8025'
const DEMO_EMAIL = 'penderie.test.20261009@example.com'
const DEMO_PASSWORD = 'PenderieTest!2026'

/**
 * Register a brand-new account through the form.
 * Yields { email, password } — the address is unique per run so repeated
 * runs never collide with the e-mail verification throttle.
 */
Cypress.Commands.add('registerViaUi', () => {
  const stamp = `${Date.now()}${Math.ceil(Math.random() * 900 + 100)}`
  const email = `e2e${stamp}@example.test`
  const username = `e2e_${stamp}`.slice(0, 30)
  const password = 'MotDePasse!2026'

  cy.visit('/inscription')
  cy.get('#register-username').type(username)
  cy.get('#register-email').type(email)
  cy.get('#register-birth-date').type('2000-01-02')
  cy.get('#register-password').type(password)
  cy.get('#register-password-confirm').type(password)
  cy.intercept('POST', '**/api/auth/register').as('registerSubmit')
  cy.contains('button', "S'inscrire").click()
  cy.wait('@registerSubmit')
  cy.contains('h1', 'Vérifiez votre e-mail').should('be.visible')

  return cy.wrap({ email, password })
})

/**
 * Read the verification e-mail from Mailpit (SMTP sink of the stack),
 * extract the token and open /verifier-email?token=…
 * (the absolute localhost link in the mail would not resolve inside the
 * cypress container, so only the token is reused).
 */
Cypress.Commands.add('verifyViaMailpit', (email) => {
  cy.request(`${MAILPIT_URL}/api/v1/messages?limit=50`).then((list) => {
    const messages = [...(list.body.messages || [])].sort(
      (a, b) => new Date(b.Created) - new Date(a.Created),
    )
    const message = messages.find((m) =>
      (m.To || []).some((recipient) => (recipient.Address ?? recipient) === email),
    )
    expect(message, `un e-mail de vérification pour ${email} trouvé dans Mailpit`).to.exist

    cy.request(`${MAILPIT_URL}/api/v1/message/${message.ID}`).then((detail) => {
      // The transactional mail is text/plain — fall back to Text if HTML is empty.
      const body = detail.body.HTML || detail.body.Text || ''
      const match = /verifier-email\?token=([^"'\s<]+)/.exec(body)
      expect(match, 'lien /verifier-email?token= présent dans le mail').to.not.equal(null)
      cy.visit(`/verifier-email?token=${decodeURIComponent(match[1])}`)
    })
  })
})

/** Confirm the verification button and expect the landing on /dressing. */
Cypress.Commands.add('confirmVerification', () => {
  cy.contains('button', 'Confirmer et créer mon compte').click()
  cy.url().should('include', '/dressing')
})

/** Log the demo account in through the form; expect the foyer chooser. */
Cypress.Commands.add('loginDemo', () => {
  cy.visit('/connexion')
  cy.get('#login-email').type(DEMO_EMAIL)
  cy.get('#login-password').type(DEMO_PASSWORD)
  cy.contains('button', 'Se connecter').click()
  cy.url().should('include', '/profils')
  cy.selectFoyer()
})

/**
 * Pick the parent profile of the demo household.
 * An account whose only household is automatically attached to it at
 * login (server-side session), so /profils shows «Qui entre ?» directly.
 */
Cypress.Commands.add('selectFoyer', () => {
  cy.contains('h1', 'Qui entre ?').should('be.visible')
  cy.contains('button', 'Camille').click()
  cy.get('nav[aria-label="Navigation principale"]').should('be.visible')
})

/**
 * Open the scanner and force the failure path (never call the real LLM):
 * a gallery upload with POST /api/scan stubbed to 503 → the fallback block
 * with «Saisir l’article manuellement» appears.
 */
Cypress.Commands.add('openManualEntry', () => {
  cy.intercept('POST', '**/api/scan', {
    statusCode: 503,
    body: { error: { code: 'scan_unavailable', message: 'Analyse indisponible.' } },
  }).as('scanFail')
  cy.visit('/scanner')
  cy.get('input[aria-label="Choisir depuis la galerie"]').selectFile(
    'cypress/fixtures/scan-sample.jpg',
    { force: true },
  )
  cy.wait('@scanFail')
  cy.contains('button', 'Saisir l’article manuellement').click()
  cy.contains('h2', 'Nouvel article').should('be.visible')
})
