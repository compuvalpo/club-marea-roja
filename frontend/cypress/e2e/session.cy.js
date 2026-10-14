describe('Sesiones y permisos', () => {
  it('no guarda tokens en el almacenamiento del navegador y mantiene la sesión al recargar', () => {
    cy.loginAs('guardian');
    cy.reload();
    cy.get('[data-testid=user-name]').should('be.visible');
    cy.window().then((win) => {
      expect(win.localStorage.length).to.eq(0);
      expect(win.sessionStorage.length).to.eq(0);
    });
  });

  it('cierra la sesión anterior cuando el mismo usuario vuelve a ingresar', () => {
    cy.loginAs('coach');
    // Un segundo ingreso del mismo usuario (otra pestaña u otro equipo) revoca la sesión actual.
    cy.request('POST', '/api/v1/auth/login', { email: 'entrenador@marea.cl', password: 'Marea2026!' });
    cy.get('[data-testid=nav-athletes]').click();
    cy.get('[data-testid=login-notice]').should('contain', 'Se inició');
  });

  it('revoca de inmediato los permisos cuando el administrador cambia el rol', () => {
    cy.loginAs('coach');
    cy.request('POST', '/api/v1/auth/login', { email: 'admin@marea.cl', password: 'Marea2026!' }).then(({ body }) => {
      const auth = { Authorization: `Bearer ${body.data.access_token}` };
      cy.request({ url: '/api/v1/users?role=coach', headers: auth }).then(({ body: users }) => {
        const coach = users.data.find((u) => u.email === 'entrenador@marea.cl');
        cy.request({ method: 'PATCH', url: `/api/v1/users/${coach.id}`, headers: auth, body: { role: 'guardian' } });
        cy.request({ method: 'PATCH', url: `/api/v1/users/${coach.id}`, headers: auth, body: { role: 'coach' } });
      });
    });
    cy.get('[data-testid=nav-calendar]').click();
    cy.get('[data-testid=login-notice]').should('be.visible');
  });

  it('la API rechaza con 403 lo que el rol no permite aunque se llame directamente', () => {
    cy.request('POST', '/api/v1/auth/login', { email: 'apoderado@marea.cl', password: 'Marea2026!' }).then(({ body }) => {
      cy.request({
        url: '/api/v1/finance/charges',
        headers: { Authorization: `Bearer ${body.data.access_token}` },
        failOnStatusCode: false,
      })
        .its('status')
        .should('eq', 403);
    });
  });

  it('muestra "Acceso denegado" al entrar por URL a una sección sin permiso', () => {
    cy.loginAs('athlete');
    cy.visit('/#/users');
    cy.contains('h1', 'Acceso denegado').should('be.visible');
  });
});
