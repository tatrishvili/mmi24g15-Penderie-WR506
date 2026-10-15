describe('Dressing : recherche et ajout d’article', () => {
  it('recherche les articles du foyer et signale les résultats vides', () => {
    cy.loginDemo()
    cy.visit('/dressing')
    // The list only reloads when the search form is submitted.
    cy.get('#items-search').type('Pull')
    cy.contains('button', 'Rechercher').click()
    cy.contains('Pull noir').should('be.visible')
    cy.get('#items-search').clear().type('zzzz-inexistant')
    cy.contains('button', 'Rechercher').click()
    // A no-match search returns a filtered total of 0, which the page
    // renders with its « empty dressing » wording (current app behaviour).
    cy.contains('Votre dressing est encore vide. Scannez votre premier article !').should(
      'be.visible',
    )
  })

  it('crée un article par la saisie manuelle du scanner', () => {
    cy.loginDemo()
    cy.openManualEntry()
    cy.get('#item-name').type('Pull e2e Cypress')
    cy.get('#item-type').select('pull')
    cy.get('#item-condition').select('bon')
    cy.contains('button', 'Enregistrer').click()
    cy.url().should('include', '/dressing')
    cy.contains('Pull e2e Cypress').should('be.visible')
  })

  it('ne soumet pas un article sans nom', () => {
    cy.loginDemo()
    cy.openManualEntry()
    cy.contains('button', 'Enregistrer').click()
    cy.url().should('include', '/scanner')
    cy.get('#item-name').then(($input) => {
      expect($input[0].checkValidity()).to.equal(false)
    })
    cy.contains('h2', 'Nouvel article').should('be.visible')
  })

  it('prête un article disponible puis le récupère', () => {
    cy.loginDemo()
    cy.visit('/dressing')

    // Self-restoring cycle: fixtures ship "Pull noir" as available, and the
    // test returns it to available so re-runs stay green without fixtures reset.
    // The list reloads after each status change, so re-query by item text.
    cy.contains('li', 'Pull noir').contains('Disponible')
    cy.contains('li', 'Pull noir').contains('button', 'Prêter').click()
    cy.contains('li', 'Pull noir').contains('Prêté')
    cy.contains('li', 'Pull noir').contains('button', 'Récupérer').click()
    cy.contains('li', 'Pull noir').contains('Disponible')
    cy.contains('li', 'Pull noir').contains('button', 'Prêter')
  })
})
