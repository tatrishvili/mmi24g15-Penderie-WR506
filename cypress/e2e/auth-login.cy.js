describe('Connexion', () => {
  it('refuse un mot de passe incorrect', () => {
    cy.visit('/connexion')
    cy.get('#login-email').type('penderie.test.20261009@example.com')
    cy.get('#login-password').type('MauvaisMotDePasse')
    cy.contains('button', 'Se connecter').click()
    cy.url().should('include', '/connexion')
    cy.get('p[role="alert"]').should('be.visible').and('not.be.empty')
  })

  it('connecte le compte de démonstration, fait choisir le foyer et montre le dressing', () => {
    cy.loginDemo()
    cy.visit('/dressing')
    cy.get('#items-search').should('be.visible')
    cy.contains('Pull noir').should('be.visible')
  })

  it('déconnecte puis bloque de nouveau les routes protégées', () => {
    cy.loginDemo()
    cy.contains('nav[aria-label="Navigation principale"] a', 'Compte').click()
    cy.url().should('include', '/compte')
    cy.contains('button', 'Se déconnecter').click()
    cy.url().should('include', '/connexion')
    cy.visit('/dressing')
    cy.url().should('include', '/connexion')
  })
})
