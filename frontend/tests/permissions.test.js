import { describe, expect, it } from 'vitest';
import { canAccess, resolve } from '../src/router.js';
import { routes } from '../src/routes.js';
import { can } from '../src/api/session.js';
import { tokenExpiry } from '../src/api/client.js';

// Permisos tal como los entrega la API (App\Auth\Permissions::for).
const PERMS = {
  admin: [
    'dashboard.view',
    'users.manage',
    'athletes.view',
    'athletes.list',
    'athletes.manage',
    'catalog.manage',
    'schedules.manage',
    'practices.view',
    'practices.plan',
    'practices.manage',
    'practices.rsvp',
    'events.view',
    'events.manage',
    'events.rsvp',
    'finance.manage',
  ],
  coach: ['dashboard.view', 'athletes.view', 'athletes.list', 'practices.view', 'practices.manage', 'events.view'],
  guardian: [
    'dashboard.view',
    'athletes.view',
    'athletes.list',
    'practices.view',
    'practices.rsvp',
    'events.view',
    'events.rsvp',
    'finance.own',
  ],
  athlete: [
    'dashboard.view',
    'athletes.view',
    'practices.view',
    'practices.rsvp',
    'events.view',
    'events.rsvp',
    'finance.own',
    'stats.own',
  ],
};
const user = (role) => ({ id: 1, role, active: true, permissions: PERMS[role] });
const navFor = (role) => routes.filter((r) => r.nav && canAccess(r, user(role))).map((r) => r.path);

describe('permisos en el frontend', () => {
  it('cada ruta declara un permiso', () => {
    routes.forEach((r) => expect(r.permission, r.path).toBeTruthy());
  });

  it('el menú refleja los permisos de cada rol', () => {
    expect(navFor('admin')).toEqual(['/', '/calendar', '/events', '/athletes', '/finance', '/groups', '/venues', '/users']);
    expect(navFor('coach')).toEqual(['/', '/calendar', '/events', '/athletes']);
    expect(navFor('guardian')).toEqual(['/', '/calendar', '/events', '/athletes', '/my-payments']);
    expect(navFor('athlete')).toEqual(['/', '/calendar', '/events', '/my-stats', '/my-payments']);
  });

  it('bloquea por URL directa las secciones sin permiso', () => {
    expect(resolve(routes, '#/finance', user('coach')).forbidden).toBe(true);
    expect(resolve(routes, '#/users', user('guardian')).forbidden).toBe(true);
    expect(resolve(routes, '#/finance', user('admin')).forbidden).toBe(false);
    expect(resolve(routes, '#/finance', null).forbidden).toBe(true);
  });

  it('can() usa la lista de permisos y rechaza usuarios inactivos o sin sesión', () => {
    expect(can('practices.plan', user('admin'))).toBe(true);
    expect(can('practices.plan', user('coach'))).toBe(false);
    expect(can('practices.plan', { ...user('admin'), active: false })).toBe(false);
    expect(can('practices.plan', null)).toBe(false);
  });
});

describe('expiración del access token', () => {
  const b64 = (o) => btoa(JSON.stringify(o)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

  it('lee el claim exp del JWT en milisegundos', () => {
    expect(tokenExpiry(`${b64({ alg: 'HS256' })}.${b64({ exp: 1700000000 })}.firma`)).toBe(1700000000000);
  });

  it('devuelve 0 si el token es inválido', () => {
    expect(tokenExpiry('basura')).toBe(0);
  });
});
