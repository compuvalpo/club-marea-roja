describe('Administrador · prácticas y horarios', () => {
  beforeEach(() => cy.loginAs('admin'));

  it('asigna una práctica desde el calendario', () => {
    cy.get('[data-testid=nav-calendar]').click();
    cy.get('[data-testid=calendar] .fc-event').should('have.length.greaterThan', 5);
    cy.get('[data-testid=new-practice]').click();
    cy.get('dialog[open] select[name=group_id]').select('Máster');
    cy.get('dialog[open] input[name=start_time]').clear();
    cy.get('dialog[open] input[name=start_time]').type('07:00');
    cy.get('dialog[open] input[name=end_time]').clear();
    cy.get('dialog[open] input[name=end_time]').type('08:00');
    cy.get('dialog[open] textarea[name=objectives]').type('Técnica de pecho');
    cy.submitModal();
    cy.contains('.toast', 'Práctica asignada').should('be.visible');
    cy.get('[data-testid=calendar]').contains('.fc-event', '07:00');
  });

  it('crea un horario semanal y genera sus prácticas', () => {
    cy.get('[data-testid=nav-groups]').click();
    cy.get('[data-testid=new-schedule]').click();
    cy.get('dialog[open] select[name=group_id]').select('Máster');
    cy.get('dialog[open] select[name=weekday]').select('Domingo');
    cy.get('dialog[open] input[name=start_time]').clear();
    cy.get('dialog[open] input[name=start_time]').type('10:00');
    cy.get('dialog[open] input[name=end_time]').clear();
    cy.get('dialog[open] input[name=end_time]').type('11:30');
    cy.submitModal();
    cy.contains('.toast', 'Horario creado').should('be.visible');
    cy.get('[data-testid=schedules-table]').contains('tr', 'Domingo').contains('button', 'Generar prácticas').click();
    cy.submitModal();
    cy.contains('.toast', /Se crearon \d+ prácticas/).should('be.visible');
  });
});
