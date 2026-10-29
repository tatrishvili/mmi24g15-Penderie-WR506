describe('Inscription et vérification du compte', () => {
  it('demande de vérifier l’e-mail après l’inscription', () => {
    cy.registerViaUi()
  })

  it('refuse l’inscription adulte pour une date de naissance mineure', () => {
    cy.visit('/inscription')
    cy.get('#register-username').type(`e2e_${Date.now()}`)
    cy.get('#register-email').type(`e2e${Date.now()}@example.test`)
    cy.get('#register-birth-date').type('2020-01-02')
    cy.get('#register-password').type('MotDePasse!2026')
    cy.get('button.register__submit').click()
    cy.get('.field-error').should('contain', '18')
    cy.url().should('include', '/inscription')
  })

  it('confirme le compte depuis le lien reçu dans Mailpit', () => {
    cy.registerViaUi().then(({ email }) => {
      cy.verifyViaMailpit(email)
      cy.confirmVerification()
    })
  })
})
