import { h, toastError } from '../ui/dom.js';
import { field, readForm } from '../ui/form.js';
import { consumeLogoutReason, login } from '../api/session.js';
import { brandLogo } from '../ui/layout.js';
import { icon } from '../ui/icons.js';

const DEMO = [
  ['Administrador', 'admin@marea.cl'],
  ['Entrenador', 'entrenador@marea.cl'],
  ['Apoderado', 'apoderado@marea.cl'],
  ['Alumno', 'alumno@marea.cl'],
];

export function loginView() {
  const submit = h('button.btn.primary.block', { type: 'submit', 'data-testid': 'login-submit' }, icon('user', { size: 16 }), 'Ingresar');
  const error = h('p.form-error', { role: 'alert', 'data-testid': 'login-error' });
  // Motivo del último cierre (expiró, inactividad, otra pestaña, permisos cambiados...).
  const reason = consumeLogoutReason();
  const notice = reason ? h('p.form-notice', { role: 'status', 'data-testid': 'login-notice' }, icon('alert', { size: 16 }), reason) : null;
  const form = h(
    'form.login-form',
    { novalidate: true },
    notice,
    field({ label: 'Correo electrónico', name: 'email', type: 'email', required: true, autocomplete: 'username' }),
    field({ label: 'Contraseña', name: 'password', type: 'password', required: true, autocomplete: 'current-password' }),
    error,
    submit,
  );

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const { email, password } = readForm(form);
    error.textContent = '';
    if (!email || !password) {
      error.textContent = 'Ingrese correo y contraseña.';
      return;
    }
    submit.disabled = true;
    try {
      await login(email, password);
      window.location.hash = '#/';
    } catch (err) {
      error.textContent = err.message;
      if (err.status >= 500) toastError(err);
    } finally {
      submit.disabled = false;
    }
  });

  const fill = (email) => {
    form.elements.namedItem('email').value = email;
    form.elements.namedItem('password').value = 'Marea2026!';
  };

  return h(
    'div.login-page',
    null,
    h(
      'section.login-card',
      null,
      h(
        'div.brand.center',
        null,
        brandLogo(72),
        h('h1.sr-only', null, 'Marea Roja · Club Deportivo'),
        h('p.muted', null, 'Gestión del club deportivo'),
      ),
      form,
      import.meta.env.DEV
        ? h(
            'details.demo',
            null,
            h('summary', null, 'Cuentas de demostración'),
            h(
              'div.demo-grid',
              null,
              DEMO.map(([label, email]) => h('button.btn.ghost.sm', { type: 'button', onClick: () => fill(email) }, label)),
            ),
          )
        : null,
    ),
  );
}
