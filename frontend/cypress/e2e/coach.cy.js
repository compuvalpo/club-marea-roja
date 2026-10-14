describe('Entrenador · gestión de la práctica del día', () => {
  beforeEach(() => cy.loginAs('coach'));

  it('ingresa objetivos, pasa lista y registra marca e incidencia', () => {
    cy.get('[data-testid=upcoming] a').first().click();

    cy.get('[data-testid=practice-plan] textarea[name=objectives]').clear();

    cy.get('[data-testid=practice-plan] textarea[name=objectives]').type('Virajes y salidas de bloque');
    cy.get('[data-testid=practice-plan]').submit();
    cy.contains('.toast', 'Planificación guardada').should('be.visible');

    cy.get('[data-testid=attendance-table] input[type=checkbox]').first().uncheck();
    cy.get('[data-testid=save-attendance]').click();
    cy.contains('.toast', 'Lista guardada').should('be.visible');

    cy.get('[data-testid=measurement-form]').within(() => {
      cy.get('select[name=athlete_id]').select(1);
      cy.get('input[name=metric]').type('25 m libre');
      cy.get('input[name=value]').type('19.85');
      cy.root().submit();
    });
    cy.get('[data-testid=measurements-table]').should('contain', '25 m libre').and('contain', '19.85 s');

    cy.get('[data-testid=incident-form]').within(() => {
      cy.get('select[name=type]').select('Lesión');
      cy.get('select[name=severity]').select('Moderada');
      cy.get('textarea[name=description]').type('Calambre en gemelo durante series.');
      cy.root().submit();
    });
    cy.get('[data-testid=incidents-table]').should('contain', 'Calambre en gemelo');
  });

  it('no puede ver prácticas de otros entrenadores en su calendario', () => {
    cy.get('[data-testid=nav-calendar]').click();
    cy.get('[data-testid=calendar] .fc-event').should('have.length.greaterThan', 0);
    cy.get('[data-testid=calendar]').should('not.contain', '18:00 Juvenil');
  });
});
