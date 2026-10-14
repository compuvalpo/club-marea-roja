import './styles/main.css';
import { currentUser, restore, subscribe } from './api/session.js';
import { resolve } from './router.js';
import { routes } from './routes.js';
import { layout } from './ui/layout.js';
import { h, mount, spinner, toastError } from './ui/dom.js';
import { loginView } from './views/login.js';
import { icon } from './ui/icons.js';

const app = document.getElementById('app');
let renderId = 0;

async function render() {
  const id = ++renderId;
  const user = currentUser();

  if (!user) {
    mount(app, loginView());
    return;
  }

  const { route, params, query, forbidden } = resolve(routes, window.location.hash, user);
  const path = (window.location.hash.replace(/^#/, '') || '/').split('?')[0];

  if (!route) {
    mount(
      app,
      layout(
        user,
        routes,
        path,
        h('section.card', null, h('h1', null, 'Página no encontrada'), h('a', { href: '#/' }, 'Volver al inicio')),
      ),
    );
    return;
  }
  if (forbidden) {
    mount(
      app,
      layout(
        user,
        routes,
        path,
        h('section.card', null, h('h1', null, 'Acceso denegado'), h('p', null, 'Su rol no tiene permisos para esta sección.')),
      ),
    );
    return;
  }

  document.title = `${route.title} · Marea Roja`;
  const content = h('div.view', null, spinner());
  mount(app, layout(user, routes, path, content));

  try {
    const view = await route.view({ params, query, user });
    if (id === renderId) {
      mount(content, view);
      // Icono de la sección junto al título de la página.
      const name = route.nav?.icon ?? route.icon;
      const h1 = content.querySelector('.page-header h1');
      if (name && h1) h1.prepend(h('span.title-icon', { 'aria-hidden': 'true' }, icon(name, { size: 26 })));
    }
  } catch (error) {
    if (id === renderId)
      mount(content, h('section.card.error-card', null, h('h1', null, 'No se pudo cargar'), h('p', null, error.message)));
    toastError(error);
  }
}

window.addEventListener('hashchange', render);
subscribe(render);

mount(app, h('div.boot', null, spinner()));
restore().finally(render);
