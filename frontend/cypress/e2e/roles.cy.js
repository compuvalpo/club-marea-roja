describe('Permisos por rol', () => {
  const navFor = {
    admin: ['nav-home', 'nav-calendar', 'nav-events', 'nav-athletes', 'nav-finance', 'nav-groups', 'nav-venues', 'nav-users'],
    coach: ['nav-home', 'nav-calendar', 'nav-events', 'nav-athletes'],
    guardian: ['nav-home', 'nav-calendar', 'nav-events', 'nav-athletes', 'nav-my-payments'],
    athlete: ['nav-home', 'nav-calendar', 'nav-events', 'nav-my-stats', 'nav-my-payments'],
  };

  Object.entries(navFor).forEach(([role, items]) => {
    it(`muestra sólo el menú permitido para ${role}`, () => {
      cy.loginAs(role);
      cy.get('.sidebar nav a').should('have.length', items.length);
      items.forEach((id) => cy.get(`[data-testid=${id}]`).should('exist'));
    });
  });

  it('bloquea el acceso directo a Finanzas para un entrenador', () => {
    cy.loginAs('coach');
    cy.visit('/#/finance');
    cy.contains('h1', 'Acceso denegado').should('be.visible');
  });

  it('muestra "Eliminar" desactivado para el propio usuario administrador', () => {
    cy.loginAs('admin');
    cy.visit('/#/users');
    cy.get('[data-testid=users-table]').contains('tr', 'admin@marea.cl').find('button.danger-outline').should('be.disabled');
    cy.get('[data-testid=users-table]').contains('tr', 'jorge.soto@marea.cl').find('button.danger-outline').should('not.be.disabled');
  });
});
