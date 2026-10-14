const PASSWORD = 'Marea2026!';

/** Inicia sesión por la interfaz (como lo haría un usuario real). */
Cypress.Commands.add('login', (email, password = PASSWORD) => {
  cy.visit('/');
  cy.get('input[name=email]').clear();
  cy.get('input[name=email]').type(email);
  cy.get('input[name=password]').clear();
  cy.get('input[name=password]').type(password, { log: false });
  cy.get('[data-testid=login-submit]').click();
  cy.get('[data-testid=user-name]').should('be.visible');
});

Cypress.Commands.add('loginAs', (role) => {
  const emails = {
    admin: 'admin@marea.cl',
    coach: 'entrenador@marea.cl',
    guardian: 'apoderado@marea.cl',
    athlete: 'alumno@marea.cl',
  };
  cy.login(emails[role]);
});

/** Envía el formulario del modal abierto. */
Cypress.Commands.add('submitModal', () => {
  cy.get('dialog[open] form').submit();
});
