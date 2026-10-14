import { h } from './dom.js';
import { icon } from './icons.js';
import { ROLE_LABELS, logout } from '../api/session.js';
import { canAccess } from '../router.js';

/**
 * Estructura principal: barra lateral con navegación filtrada por permisos + contenido.
 */
export function layout(user, routes, currentPath, content) {
  const navItems = routes
    .filter((r) => r.nav && canAccess(r, user))
    .map((r) => {
      const active = r.path === '/' ? currentPath === '/' : currentPath.startsWith(r.path);
      return h(
        'a.nav-link',
        { href: `#${r.path}`, 'aria-current': active ? 'page' : null, dataset: { testid: `nav-${r.path.slice(1) || 'home'}` } },
        h('span.nav-icon', { 'aria-hidden': 'true' }, icon(r.nav.icon)),
        h('span', null, r.nav.label ?? r.title),
      );
    });

  const sidebar = h(
    'aside.sidebar',
    { id: 'sidebar' },
    h('a.brand', { href: '#/', 'aria-label': 'Marea Roja · Club Deportivo — Inicio' }, brandLogo(54)),
    h('nav', { 'aria-label': 'Principal' }, navItems),
    h(
      'div.user-box',
      null,
      h(
        'div',
        null,
        h('strong', { 'data-testid': 'user-name' }, user.name),
        h('small', { 'data-testid': 'user-role' }, ROLE_LABELS[user.role]),
      ),
      h(
        'button.btn.ghost.sm',
        { type: 'button', 'data-testid': 'logout', onClick: () => logout().then(() => (window.location.hash = '#/')) },
        icon('logout', { size: 16 }),
        'Salir',
      ),
    ),
  );

  const toggle = h(
    'button.icon-btn.menu-toggle',
    { type: 'button', 'aria-label': 'Menú', 'aria-controls': 'sidebar', onClick: () => sidebar.classList.toggle('open') },
    icon('menu', { size: 22 }),
  );
  sidebar.addEventListener('click', (e) => {
    if (e.target.closest('a')) sidebar.classList.remove('open');
  });

  return h('div.shell', null, sidebar, h('main.content', { id: 'main', tabindex: '-1' }, toggle, content));
}

export function pageHeader(title, subtitle, ...actions) {
  return h(
    'header.page-header',
    null,
    h('div', null, h('h1', null, title), subtitle ? h('p.muted', null, subtitle) : null),
    h('div.page-actions', null, actions),
  );
}

export function card(title, ...children) {
  return h('section.card', null, title ? h('h2.card-title', null, title) : null, children);
}

/**
 * Tarjeta de indicador. `iconName` (opcional) es un nombre de ui/icons.js.
 */
export function kpi(label, value, variant = '', testId, iconName) {
  return h(
    `div.kpi${variant ? `.${variant}` : ''}`,
    { 'data-testid': testId },
    iconName ? h('span.kpi-icon', { 'aria-hidden': 'true' }, icon(iconName, { size: 20 })) : null,
    h('div', null, h('small', null, label), h('strong', null, value)),
  );
}

/**
 * Logo horizontal del club con variante para tema oscuro.
 * @param {number} height  Alto en píxeles (el ancho se ajusta a la proporción 300×80).
 */
export function brandLogo(height = 44) {
  const width = Math.round((height * 300) / 80);
  return h(
    'picture.brand-logo',
    null,
    h('source', { srcset: '/brand/logo-horizontal-dark.svg', media: '(prefers-color-scheme: dark)' }),
    h('img', { src: '/brand/logo-horizontal.svg', alt: 'Marea Roja · Club Deportivo', width, height }),
  );
}
