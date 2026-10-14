describe('Eventos en otros recintos', () => {
  it('el administrador crea un evento en un lugar libre y convoca alumnos', () => {
    cy.loginAs('admin');
    cy.get('[data-testid=nav-events]').click();
    cy.get('[data-testid=new-event]').click();
    cy.get('dialog[open] input[name=title]').type('Clínica de aguas abiertas');
    cy.get('dialog[open] input[value=text]').check();
    cy.get('dialog[open] input[name=location_text]').type('Playa Las Torpederas, Valparaíso');
    cy.submitModal();
    cy.contains('.toast', 'Evento creado').should('be.visible');

    cy.get('[data-testid=events-table]').contains('tr', 'Clínica de aguas abiertas').contains('button', 'Convocar').click();
    cy.get('dialog[open] .check input').first().check();
    cy.get('dialog[open]').contains('button', 'Convocar').click();
    cy.contains('.toast', 'convocados').should('be.visible');
  });

  it('el apoderado confirma la participación de su hijo en una competencia', () => {
    cy.loginAs('guardian');
    cy.get('[data-testid=nav-events]').click();
    cy.get('[data-testid=events-table]').contains('button', 'Campeonato Regional').click();
    cy.get('dialog[open]').contains('button', 'Confirmar').click();
    cy.contains('.toast', 'Participación confirmada').should('be.visible');
  });
});
