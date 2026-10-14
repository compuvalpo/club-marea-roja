import { h, badge, mount, table, toast, toastError } from '../ui/dom.js';
import { field } from '../ui/form.js';
import { confirmDialog, formModal } from '../ui/modal.js';
import { card, pageHeader } from '../ui/layout.js';
import { ROLE_LABELS, currentUser } from '../api/session.js';
import { del, get, patch, post } from '../api/client.js';
import { icon } from '../ui/icons.js';

/** Gestión de usuarios y roles (Admin). */
const isSelf = (u) => u.id === currentUser()?.id;

export async function usersView() {
  let users = await get('/users');
  let role = '';
  const box = h('div');
  const roleOptions = Object.entries(ROLE_LABELS).map(([value, label]) => ({ value, label }));

  const fields = (u = {}) => [
    field({ label: 'Nombre', name: 'name', required: true, value: u.name }),
    field({ label: 'Correo', name: 'email', type: 'email', required: true, value: u.email }),
    // El propio administrador no puede quitarse el rol ni desactivarse (la API también lo impide).
    field({
      label: 'Rol',
      name: 'role',
      type: 'select',
      required: true,
      value: u.role ?? 'guardian',
      options: roleOptions,
      disabled: isSelf(u),
    }),
    field({ label: 'Teléfono', name: 'phone', value: u.phone }),
    field({
      label: u.id ? 'Nueva contraseña (opcional)' : 'Contraseña',
      name: 'password',
      type: 'password',
      required: !u.id,
      hint: 'Mínimo 12 caracteres. Puede usar una frase. Cambiarla cierra las sesiones abiertas del usuario.',
      autocomplete: 'new-password',
    }),
    field({ label: 'Activo', name: 'active', type: 'checkbox', value: u.active ?? true, disabled: isSelf(u) }),
  ];

  const reload = async () => {
    users = await get('/users');
    render();
  };

  function render() {
    mount(
      box,
      table(
        [
          { label: 'Nombre', render: (u) => u.name },
          { label: 'Correo', render: (u) => u.email },
          { label: 'Rol', render: (u) => badge(ROLE_LABELS[u.role], 'info') },
          { label: 'Teléfono', render: (u) => u.phone ?? '—' },
          { label: 'Estado', render: (u) => (u.active ? badge('Activo', 'success') : badge('Bloqueado', 'danger')) },
          {
            label: '',
            class: 'actions-cell',
            render: (u) =>
              h(
                'div.row-actions',
                null,
                h(
                  'button.btn.sm.ghost',
                  {
                    type: 'button',
                    onClick: () =>
                      formModal(`Editar ${u.name}`, fields(u), async (d) => {
                        if (!d.password) delete d.password;
                        await patch(`/users/${u.id}`, d);
                        const sensitive = (d.role && d.role !== u.role) || (d.active !== undefined && d.active !== u.active) || d.password;
                        toast(sensitive && !isSelf(u) ? 'Usuario actualizado. Sus sesiones abiertas se cerraron.' : 'Usuario actualizado.');
                        await reload();
                      }),
                  },
                  'Editar',
                ),
                h(
                  'button.btn.sm.ghost',
                  {
                    type: 'button',
                    title: 'Cierra la sesión del usuario en todas las pestañas y dispositivos',
                    'data-testid': `revoke-sessions-${u.id}`,
                    onClick: async () => {
                      const msg = isSelf(u) ? '¿Cerrar sus otras sesiones abiertas?' : `¿Cerrar todas las sesiones de ${u.name}?`;
                      if (!(await confirmDialog(msg, { confirmLabel: 'Cerrar sesiones' }))) return;
                      try {
                        const { revoked } = await post(`/users/${u.id}/revoke-sessions`);
                        toast(revoked ? `Se cerraron ${revoked} sesión(es).` : 'No había sesiones abiertas.');
                      } catch (error) {
                        toastError(error);
                      }
                    },
                  },
                  'Cerrar sesiones',
                ),
                h(
                  'button.btn.sm.danger-outline',
                  {
                    type: 'button',
                    disabled: isSelf(u),
                    title: isSelf(u) ? 'No puede eliminar su propio usuario' : undefined,
                    'data-testid': `delete-user-${u.id}`,
                    onClick: async () => {
                      if (isSelf(u)) return;
                      if (!(await confirmDialog(`¿Eliminar a ${u.name}?`, { danger: true, confirmLabel: 'Eliminar' }))) return;
                      await del(`/users/${u.id}`).catch(toastError);
                      await reload();
                    },
                  },
                  'Eliminar',
                ),
              ),
          },
        ],
        users.filter((u) => !role || u.role === role),
        { testId: 'users-table' },
      ),
    );
  }

  render();

  return h(
    'div',
    null,
    pageHeader(
      'Usuarios',
      'Cuentas de administradores, entrenadores, apoderados y alumnos.',
      h(
        'select.inline-select',
        { 'aria-label': 'Filtrar por rol', onChange: (e) => ((role = e.target.value), render()) },
        h('option', { value: '' }, 'Todos los roles'),
        roleOptions.map((o) => h('option', { value: o.value }, o.label)),
      ),
      h(
        'button.btn.primary',
        {
          type: 'button',
          'data-testid': 'new-user',
          onClick: () =>
            formModal('Nuevo usuario', fields(), async (d) => {
              await post('/users', d);
              toast('Usuario creado.');
              await reload();
            }),
        },
        icon('plus', { size: 16 }),
        'Nuevo usuario',
      ),
    ),
    card(null, box),
  );
}
