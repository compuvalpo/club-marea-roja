import { h, mount, table, toast, toastError } from '../ui/dom.js';
import { field, options } from '../ui/form.js';
import { confirmDialog, formModal } from '../ui/modal.js';
import { card, pageHeader } from '../ui/layout.js';
import { WEEKDAYS, formatDate, isoDate } from '../ui/format.js';
import { del, get, patch, post } from '../api/client.js';
import { icon } from '../ui/icons.js';

/**
 * Grupos de entrenamiento y horarios semanales recurrentes (Admin).
 * Las prácticas se generan desde los horarios para un rango de fechas.
 */
export async function groupsView() {
  const [coaches, venues] = await Promise.all([get('/users', { role: 'coach' }), get('/venues')]);
  let groups = await get('/groups');
  let schedules = await get('/schedules');
  const groupsBox = h('div');
  const schedulesBox = h('div');

  const reload = async () => {
    [groups, schedules] = await Promise.all([get('/groups'), get('/schedules')]);
    render();
  };

  const groupFields = (g = {}) => [
    field({ label: 'Nombre', name: 'name', required: true, value: g.name }),
    field({ label: 'Descripción', name: 'description', value: g.description }),
    field({ label: 'Nivel', name: 'level', value: g.level }),
    field({ label: 'Entrenador a cargo', name: 'coach_id', type: 'select', value: g.coach_id, options: options(coaches) }),
    field({ label: 'Color en calendario', name: 'color', type: 'color', value: g.color ?? '#be123c' }),
  ];

  const scheduleFields = (s = {}) => [
    field({ label: 'Grupo', name: 'group_id', type: 'select', required: true, value: s.group_id, options: options(groups) }),
    field({ label: 'Entrenador', name: 'coach_id', type: 'select', value: s.coach_id, options: options(coaches) }),
    field({
      label: 'Recinto',
      name: 'venue_id',
      type: 'select',
      value: s.venue_id,
      options: options(venues.filter((v) => v.is_training_venue)),
    }),
    field({
      label: 'Día',
      name: 'weekday',
      type: 'select',
      required: true,
      value: s.weekday ?? 1,
      options: WEEKDAYS.slice(1).map((d, i) => ({ value: i + 1, label: d })),
    }),
    field({ label: 'Inicio', name: 'start_time', type: 'time', required: true, value: s.start_time?.slice(0, 5) ?? '17:00' }),
    field({ label: 'Término', name: 'end_time', type: 'time', required: true, value: s.end_time?.slice(0, 5) ?? '18:30' }),
    field({ label: 'Vigente desde', name: 'valid_from', type: 'date', required: true, value: s.valid_from ?? isoDate() }),
    field({ label: 'Vigente hasta', name: 'valid_to', type: 'date', value: s.valid_to }),
  ];

  const generate = (s) => {
    const today = new Date();
    const end = new Date(today.getFullYear(), today.getMonth() + 2, 0);
    formModal(
      `Generar prácticas · ${s.group_name} (${WEEKDAYS[s.weekday]})`,
      [
        field({ label: 'Desde', name: 'from', type: 'date', required: true, value: isoDate(today) }),
        field({
          label: 'Hasta',
          name: 'to',
          type: 'date',
          required: true,
          value: isoDate(end),
          hint: 'Máximo 180 días. Las fechas ya existentes se omiten.',
        }),
      ],
      async (data) => {
        const result = await post(`/schedules/${s.id}/generate`, data);
        toast(`Se crearon ${result.created} prácticas (${result.skipped} ya existían).`);
      },
      { submitLabel: 'Generar' },
    );
  };

  const confirmDelete = async (message, path) => {
    if (!(await confirmDialog(message, { danger: true, confirmLabel: 'Eliminar' }))) return;
    try {
      await del(path);
      toast('Eliminado.');
      await reload();
    } catch (error) {
      toastError(error);
    }
  };

  function render() {
    mount(
      groupsBox,
      table(
        [
          { label: 'Grupo', render: (g) => h('span.dot-label', null, h('i', { style: { background: g.color } }), g.name) },
          { label: 'Nivel', render: (g) => g.level ?? '—' },
          { label: 'Entrenador', render: (g) => g.coach_name ?? '—' },
          { label: 'Alumnos', render: (g) => g.athletes_count, class: 'num' },
          {
            label: '',
            class: 'actions-cell',
            render: (g) =>
              h(
                'div.row-actions',
                null,
                h(
                  'button.btn.sm.ghost',
                  {
                    type: 'button',
                    onClick: () =>
                      formModal(
                        'Editar grupo',
                        groupFields(g),
                        async (d) => (await patch(`/groups/${g.id}`, d), toast('Grupo actualizado.'), reload()),
                      ),
                  },
                  'Editar',
                ),
                h(
                  'button.btn.sm.danger-outline',
                  { type: 'button', onClick: () => confirmDelete(`¿Eliminar el grupo ${g.name}?`, `/groups/${g.id}`) },
                  'Eliminar',
                ),
              ),
          },
        ],
        groups,
        { empty: 'No hay grupos.' },
      ),
    );
    mount(
      schedulesBox,
      table(
        [
          { label: 'Día', render: (s) => WEEKDAYS[s.weekday] },
          { label: 'Horario', render: (s) => `${s.start_time.slice(0, 5)}–${s.end_time.slice(0, 5)}` },
          { label: 'Grupo', render: (s) => s.group_name },
          { label: 'Entrenador', render: (s) => s.coach_name ?? '—' },
          { label: 'Recinto', render: (s) => s.venue_name ?? '—' },
          { label: 'Vigencia', render: (s) => `${formatDate(s.valid_from)} → ${s.valid_to ? formatDate(s.valid_to) : 'indefinida'}` },
          {
            label: '',
            class: 'actions-cell',
            render: (s) =>
              h(
                'div.row-actions',
                null,
                h('button.btn.sm.primary', { type: 'button', onClick: () => generate(s) }, 'Generar prácticas'),
                h(
                  'button.btn.sm.ghost',
                  {
                    type: 'button',
                    onClick: () =>
                      formModal(
                        'Editar horario',
                        scheduleFields(s),
                        async (d) => (await patch(`/schedules/${s.id}`, d), toast('Horario actualizado.'), reload()),
                      ),
                  },
                  'Editar',
                ),
                h(
                  'button.btn.sm.danger-outline',
                  {
                    type: 'button',
                    onClick: () => confirmDelete('¿Eliminar este horario? Las prácticas ya generadas se conservan.', `/schedules/${s.id}`),
                  },
                  'Eliminar',
                ),
              ),
          },
        ],
        schedules,
        { empty: 'No hay horarios definidos.', testId: 'schedules-table' },
      ),
    );
  }

  render();

  return h(
    'div',
    null,
    pageHeader('Grupos y horarios', 'Defina horarios semanales y genere las prácticas del calendario en un clic.'),
    card(
      'Horarios semanales',
      h(
        'div.actions.end',
        null,
        h(
          'button.btn.primary',
          {
            type: 'button',
            'data-testid': 'new-schedule',
            onClick: () =>
              formModal('Nuevo horario', scheduleFields(), async (d) => (await post('/schedules', d), toast('Horario creado.'), reload())),
          },
          icon('plus', { size: 16 }),
          'Nuevo horario',
        ),
      ),
      schedulesBox,
    ),
    card(
      'Grupos',
      h(
        'div.actions.end',
        null,
        h(
          'button.btn.primary',
          {
            type: 'button',
            onClick: () =>
              formModal('Nuevo grupo', groupFields(), async (d) => (await post('/groups', d), toast('Grupo creado.'), reload())),
          },
          icon('plus', { size: 16 }),
          'Nuevo grupo',
        ),
      ),
      groupsBox,
    ),
  );
}
