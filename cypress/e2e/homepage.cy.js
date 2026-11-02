describe('Page d’accueil (visiteur)', () => {
  it('affiche le titre, les accès principaux et l’état de l’API', () => {
    cy.intercept('GET', '**/api/health').as('health')
    cy.visit('/')
    cy.get('h1').should('be.visible').and('contain', 'Votre dressing, dans votre téléphone.')
    cy.contains('a', 'Créer mon foyer').should('have.attr', 'href', '/inscription')
    cy.contains('a', 'Se connecter').should('have.attr', 'href', '/connexion')
    cy.wait('@health')
    cy.get('[data-testid="api-badge"]').should('contain', 'API en ligne')
  })

  it('redirige une route protégée vers la connexion', () => {
    cy.visit('/dressing')
    cy.url().should('include', '/connexion')
  })
})
