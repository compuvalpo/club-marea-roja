describe('Autenticación', () => {
  it('rechaza credenciales inválidas', () => {
    cy.visit('/');
    cy.get('input[name=email]').type('admin@marea.cl');
    cy.get('input[name=password]').type('incorrecta');
    cy.get('[data-testid=login-submit]').click();
    cy.get('[data-testid=login-error]').should('contain', 'Credenciales inválidas');
  });

  it('mantiene la sesión al recargar (refresh token en cookie HttpOnly) y permite salir', () => {
    cy.loginAs('admin');
    cy.get('[data-testid=user-role]').should('have.text', 'Administrador');
    cy.reload();
    cy.get('[data-testid=user-name]').should('have.text', 'Marcela Díaz');
    cy.window().then((win) => {
      expect(win.localStorage.length, 'no se guardan tokens en localStorage').to.eq(0);
    });
    cy.get('[data-testid=logout]').click();
    cy.get('[data-testid=login-submit]').should('be.visible');
  });
});
