describe('Administrador · finanzas', () => {
  beforeEach(() => cy.loginAs('admin'));

  it('lista morosos y registra un pago que reduce la deuda', () => {
    cy.visit('/#/finance?tab=delinquents');
    cy.get('[data-testid=delinquents-table]').should('contain', 'Matías Herrera');
    cy.get('[data-testid=delinquents-table] tbody tr')
      .its('length')
      .then((before) => {
        cy.get('[data-testid=delinquents-table] tbody tr').first().contains('button', 'Registrar pago').click();
        cy.get('dialog[open] select[name=method]').select('Efectivo');
        cy.submitModal();
        cy.contains('.toast', 'Pago registrado').should('be.visible');
        if (before === 1) {
          cy.contains('Sin morosos').should('be.visible');
        } else {
          cy.get('[data-testid=delinquents-table] tbody tr').should('have.length', before - 1);
        }
      });
  });

  it('muestra quiénes no han pagado este mes', () => {
    cy.visit('/#/finance?tab=delinquents');
    cy.get('select[aria-label=Vista]').select('Quiénes no han pagado este mes');
    cy.get('[data-testid=delinquents-table] tbody tr').should('have.length', 3);
  });

  it('emite mensualidades y registra un egreso', () => {
    cy.visit('/#/finance?tab=charges');
    cy.get('[data-testid=emit-charges]').click();
    cy.contains('.toast', 'Mensualidades emitidas').should('be.visible');

    cy.contains('[role=tab]', 'Ingresos y egresos').click();
    cy.get('[data-testid=add-expense]').click();
    cy.get('dialog[open] input[name=category]').type('Transporte');
    cy.get('dialog[open] input[name=amount]').type('45000');
    cy.get('dialog[open] input[name=description]').type('Bus a campeonato');
    cy.submitModal();
    cy.get('[data-testid=transactions-table]').should('contain', 'Bus a campeonato');
  });
});
