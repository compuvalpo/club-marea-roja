describe('Apoderado y alumno', () => {
  it('el apoderado confirma y cancela la asistencia de su pupilo', () => {
    cy.loginAs('guardian');
    cy.get('[data-testid=upcoming] .rsvp').first().as('rsvp');
    cy.get('@rsvp').contains('button', 'Confirmar').click();
    cy.get('@rsvp').find('.badge').should('have.text', 'Confirmado');
    cy.get('@rsvp').contains('button', 'Cancelar').click();
    cy.get('@rsvp').find('.badge').should('have.text', 'Cancelado');
  });

  it('el apoderado ve sus pagos y atrasos', () => {
    cy.loginAs('guardian');
    cy.get('[data-testid=nav-my-payments]').click();
    cy.get('[data-testid=my-overdue]').should('contain', '1');
    cy.get('[data-testid=my-charges]').should('contain', 'Atrasado').and('contain', 'Matías Herrera');
  });

  it('el apoderado revisa las estadísticas de su hijo', () => {
    cy.loginAs('guardian');
    cy.contains('a.chip', 'Matías Herrera').click();
    cy.get('[data-testid=stats-kpis]').should('contain', 'Asistencia');
    cy.contains('h2', '50 m libre').should('be.visible');
  });

  it('el alumno ve sus estadísticas y no ve datos de otros', () => {
    cy.loginAs('athlete');
    cy.get('[data-testid=nav-my-stats]').click();
    cy.contains('h1', 'Matías Herrera');
    cy.visit('/#/athletes/4/stats');
    cy.contains('.toast', 'permisos').should('be.visible');
  });
});
