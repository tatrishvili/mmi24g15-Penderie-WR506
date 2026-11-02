describe('Foyer à la vérification du compte', () => {
  it('parcours complet : inscription, vérification, foyer auto-créé, profil, dressing', () => {
    cy.registerViaUi().then(({ email }) => {
      cy.verifyViaMailpit(email)
      cy.confirmVerification()
    })

    // À la vérification, le compte reçoit automatiquement son foyer
    // « Mon dressing » et le profil parent « Moi » (AuthService::verifyEmail).
    cy.visit('/profils')
    cy.contains('h1', 'Qui entre ?').should('be.visible')
    cy.contains(/Foyer « Mon dressing »/).should('be.visible')
    cy.contains('button', 'Moi').should('be.visible').click()
    cy.get('nav[aria-label="Navigation principale"]').should('be.visible')

    // Le dressing du nouveau foyer est accessible — et encore vide.
    cy.visit('/dressing')
    cy.get('#items-search').should('be.visible')
    cy.contains('Votre dressing est encore vide. Scannez votre premier article !').should(
      'be.visible',
    )
  })
})
