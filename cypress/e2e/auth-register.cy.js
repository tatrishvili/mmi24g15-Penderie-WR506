describe('Inscription et vérification du compte', () => {
  it('demande de vérifier l’e-mail après l’inscription', () => {
    cy.registerViaUi()
  })

  it('refuse un mot de passe de confirmation différent', () => {
    cy.visit('/inscription')
    cy.get('#register-username').type(`e2e_${Date.now()}`)
    cy.get('#register-email').type(`e2e${Date.now()}@example.test`)
    cy.get('#register-birth-date').type('2000-01-02')
    cy.get('#register-password').type('MotDePasse!2026')
    cy.get('#register-password-confirm').type('AutreMotDePasse!2026')
    cy.contains('button', "S'inscrire").click()
    cy.contains('La confirmation ne correspond pas au mot de passe.').should('be.visible')
    cy.url().should('include', '/inscription')
  })

  it('confirme le compte depuis le lien reçu dans Mailpit', () => {
    cy.registerViaUi().then(({ email }) => {
      cy.verifyViaMailpit(email)
      cy.confirmVerification()
    })
  })
})
