import { h, badge, mount, table, toast, toastError } from '../ui/dom.js';
import { field, options } from '../ui/form.js';
import { confirmDialog, formModal } from '../ui/modal.js';
import { card, pageHeader } from '../ui/layout.js';
import { formatCLP, formatDate } from '../ui/format.js';
import { del, get, patch, post } from '../api/client.js';
import { can } from '../api/session.js';
import { icon } from '../ui/icons.js';

export async function athletesView({ user }) {
  const canManage = can('athletes.manage', user); // la API vuelve a validar el permiso
  const [groups, guardians, athleteUsers] = await Promise.all([
    get('/groups'),
    canManage && can('users.manage', user) ? get('/users', { role: 'guardian' }) : [],
    canManage && can('users.manage', user) ? get('/users', { role: 'athlete' }) : [],
  ]);
  let athletes = await get('/athletes');
  let search = '';
  let group = '';
  const list = h('div');

  const render = () => {
    const q = search.toLowerCase();
    const rows = athletes.filter((a) => (!q || a.full_name.toLowerCase().includes(q)) && (!group || String(a.group_id) === group));
    const columns = [
      { label: 'Nombre', render: (a) => h('a', { href: `#/athletes/${a.id}/stats` }, a.full_name) },
      { label: 'Grupo', render: (a) => a.group_name ?? '—' },
      { label: 'Nacimiento', render: (a) => formatDate(a.birth_date) },
      { label: 'Apoderado', render: (a) => a.guardian_name ?? '—' },
      { label: 'Estado', render: (a) => (a.active ? badge('Activo', 'success') : badge('Inactivo', 'neutral')) },
    ];
    if (canManage) {
      columns.splice(4, 0, { label: 'Mensualidad', render: (a) => formatCLP(a.monthly_fee), class: 'num' });
      columns.push({
        label: '',
        class: 'actions-cell',
        render: (a) =>
          h(
            'div.row-actions',
            null,
            h('button.btn.sm.ghost', { type: 'button', onClick: () => edit(a) }, 'Editar'),
            h('button.btn.sm.danger-outline', { type: 'button', onClick: () => remove(a) }, 'Eliminar'),
          ),
      });
    }
    mount(list, table(columns, rows, { empty: 'No hay alumnos que coincidan.', testId: 'athletes-table' }));
  };

  const reload = async () => {
    athletes = await get('/athletes');
    render();
  };

  const formFields = (a = {}) => [
    field({ label: 'Nombres', name: 'first_name', required: true, value: a.first_name }),
    field({ label: 'Apellidos', name: 'last_name', required: true, value: a.last_name }),
    field({ label: 'Fecha de nacimiento', name: 'birth_date', type: 'date', value: a.birth_date }),
    field({ label: 'Grupo', name: 'group_id', type: 'select', value: a.group_id, options: options(groups) }),
    field({ label: 'Apoderado', name: 'guardian_id', type: 'select', value: a.guardian_id, options: options(guardians) }),
    field({
      label: 'Usuario del alumno',
      name: 'user_id',
      type: 'select',
      value: a.user_id,
      options: options(athleteUsers, (u) => `${u.name} (${u.email})`),
      hint: 'Opcional: para que el alumno ingrese al sistema.',
    }),
    field({
      label: 'Mensualidad (CLP)',
      name: 'monthly_fee',
      type: 'number',
      min: 0,
      step: '1000',
      required: true,
      value: a.monthly_fee ?? 30000,
    }),
    field({ label: 'Activo', name: 'active', type: 'checkbox', value: a.active ?? true }),
  ];

  const create = () =>
    formModal('Nuevo alumno', formFields(), async (data) => {
      await post('/athletes', data);
      toast('Alumno creado.');
      await reload();
    });

  const edit = (a) =>
    formModal(`Editar ${a.full_name}`, formFields(a), async (data) => {
      await patch(`/athletes/${a.id}`, data);
      toast('Alumno actualizado.');
      await reload();
    });

  const remove = async (a) => {
    if (
      !(await confirmDialog(`¿Eliminar a ${a.full_name}? Se borrarán su asistencia, marcas y cargos.`, {
        danger: true,
        confirmLabel: 'Eliminar',
      }))
    )
      return;
    try {
      await del(`/athletes/${a.id}`);
      toast('Alumno eliminado.');
      await reload();
    } catch (error) {
      toastError(error);
    }
  };

  render();

  return h(
    'div',
    null,
    pageHeader(
      user.role === 'guardian' ? 'Mis pupilos' : 'Alumnos',
      `${athletes.length} registrados`,
      canManage
        ? h(
            'button.btn.primary',
            { type: 'button', 'data-testid': 'new-athlete', onClick: create },
            icon('plus', { size: 16 }),
            'Nuevo alumno',
          )
        : null,
    ),
    card(
      null,
      h(
        'div.toolbar',
        null,
        h('input.search', {
          type: 'search',
          placeholder: 'Buscar por nombre…',
          'aria-label': 'Buscar alumno',
          onInput: (e) => ((search = e.target.value), render()),
        }),
        h(
          'select.inline-select',
          { 'aria-label': 'Filtrar por grupo', onChange: (e) => ((group = e.target.value), render()) },
          h('option', { value: '' }, 'Todos los grupos'),
          groups.map((g) => h('option', { value: g.id }, g.name)),
        ),
      ),
      list,
    ),
  );
}
