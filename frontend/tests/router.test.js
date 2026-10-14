import { describe, expect, it } from 'vitest';
import { matchPath, resolve } from '../src/router.js';

const routes = [
  { path: '/', title: 'Inicio', view: () => null, permission: 'dashboard.view' },
  { path: '/practices/:id', title: 'Práctica', view: () => null, permission: 'practices.view' },
  { path: '/finance', title: 'Finanzas', view: () => null, permission: 'finance.manage' },
];
const admin = { permissions: ['dashboard.view', 'practices.view', 'finance.manage'] };
const coach = { permissions: ['dashboard.view', 'practices.view'] };

describe('router', () => {
  it('extrae parámetros de la ruta', () => {
    expect(matchPath('/practices/:id', '/practices/42')).toEqual({ id: '42' });
    expect(matchPath('/practices/:id', '/practices')).toBeNull();
  });

  it('marca como prohibida una ruta sin el permiso', () => {
    expect(resolve(routes, '#/finance', coach).forbidden).toBe(true);
    expect(resolve(routes, '#/finance', admin).forbidden).toBe(false);
  });

  it('interpreta query string', () => {
    const r = resolve(routes, '#/finance?tab=delinquents', admin);
    expect(r.query.get('tab')).toBe('delinquents');
  });

  it('devuelve null para rutas desconocidas', () => {
    expect(resolve(routes, '#/nada', admin).route).toBeNull();
  });
});
