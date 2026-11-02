describe('Page d’accueil (visiteur)', () => {
  it('affiche l’accueil original et les accès à l’onboarding et à la connexion', () => {
    cy.visit('/')
    cy.get('h1').should('be.visible').and('contain', 'Bienvenue chez Penderie')
    cy.contains('a', 'Suivant').should('have.attr', 'href', '/onboarding/2')
    cy.contains('a', 'Se connecter').should('have.attr', 'href', '/connexion')
  })

  it('redirige une route protégée vers la connexion', () => {
    cy.visit('/dressing')
    cy.url().should('include', '/connexion')
  })
})
