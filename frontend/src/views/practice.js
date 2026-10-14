import { h, badge, mount, table, toast, toastError } from '../ui/dom.js';
import { field, options, readForm, showErrors } from '../ui/form.js';
import { confirmDialog } from '../ui/modal.js';
import { card, pageHeader } from '../ui/layout.js';
import { LABELS, RSVP_VARIANT, formatDate, formatWeekday } from '../ui/format.js';
import { ApiError, del, get, patch, post, put } from '../api/client.js';
import { can } from '../api/session.js';
import { navigate } from '../router.js';
import { rsvpControls } from './shared.js';

/**
 * Detalle de práctica.
 * - Entrenador asignado / Admin: objetivos, pasar lista, marcas e incidencias.
 * - Apoderado / Alumno: información y confirmación de asistencia.
 */
export async function practiceView({ params, user }) {
  const [practice, venues] = await Promise.all([get(`/practices/${params.id}`), get('/venues')]);
  const p = practice;

  const header = pageHeader(
    `${p.group_name} · ${p.start_time}–${p.end_time}`,
    `${formatWeekday(p.date)} · ${p.venue_name ?? 'Recinto por definir'} · Entrenador: ${p.coach_name ?? 'sin asignar'}`,
    badge(LABELS.practiceStatus[p.status], p.status === 'cancelled' ? 'danger' : p.status === 'done' ? 'success' : 'neutral'),
    can('practices.plan', user)
      ? h(
          'button.btn.danger-outline',
          {
            type: 'button',
            onClick: async () => {
              if (
                await confirmDialog('¿Eliminar esta práctica? Se perderá la asistencia registrada.', {
                  danger: true,
                  confirmLabel: 'Eliminar',
                })
              ) {
                await del(`/practices/${p.id}`).catch(toastError);
                navigate('/calendar');
              }
            },
          },
          'Eliminar',
        )
      : null,
  );

  if (!p.can_manage) {
    return h(
      'div',
      null,
      header,
      card('Objetivos de la práctica', h('p', null, p.objectives || 'El entrenador aún no registra objetivos.')),
      card(
        'Asistencia',
        p.status === 'scheduled'
          ? h(
              'div.rsvp-list',
              null,
              p.attendance.map((a) => rsvpControls(p.id, a)),
            )
          : table(
              [
                { label: 'Alumno', render: (a) => a.full_name },
                { label: 'Confirmación', render: (a) => badge(LABELS.rsvp[a.rsvp], RSVP_VARIANT[a.rsvp]) },
                { label: 'Asistió', render: (a) => (a.present === null ? '—' : a.present ? 'Sí' : 'No') },
              ],
              p.attendance,
            ),
      ),
    );
  }

  return h(
    'div',
    null,
    header,
    h('div.grid-2', null, planCard(p, venues), attendanceCard(p)),
    h('div.grid-2', null, measurementsCard(p), incidentsCard(p)),
  );
}

function planCard(p, venues) {
  const form = h(
    'form.form-grid',
    { 'data-testid': 'practice-plan' },
    field({
      label: 'Objetivos del día',
      name: 'objectives',
      type: 'textarea',
      value: p.objectives,
      rows: 4,
      placeholder: 'Ej.: técnica de virajes, series 8x50…',
    }),
    field({ label: 'Observaciones', name: 'notes', type: 'textarea', value: p.notes }),
    field({ label: 'Inicio', name: 'start_time', type: 'time', value: p.start_time, required: true }),
    field({ label: 'Término', name: 'end_time', type: 'time', value: p.end_time, required: true }),
    field({ label: 'Recinto', name: 'venue_id', type: 'select', value: p.venue_id, options: options(venues) }),
    field({
      label: 'Estado',
      name: 'status',
      type: 'select',
      required: true,
      value: p.status,
      options: Object.entries(LABELS.practiceStatus).map(([value, label]) => ({ value, label })),
    }),
    h('div.actions', null, h('button.btn.primary', { type: 'submit' }, 'Guardar planificación')),
  );
  form.elements.namedItem('status').dataset.keep = 'string';
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      await patch(`/practices/${p.id}`, readForm(form));
      toast('Planificación guardada.');
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) showErrors(form, error.errors);
      else toastError(error);
    }
  });
  return card('Planificación', form);
}

function attendanceCard(p) {
  const rows = p.attendance.map((a) => {
    const present = h('input', {
      type: 'checkbox',
      checked: a.present ?? a.rsvp !== 'cancelled',
      'aria-label': `Asistió ${a.full_name}`,
      dataset: { athlete: a.athlete_id },
    });
    const comment = h('input.compact', {
      type: 'text',
      value: a.comment ?? '',
      placeholder: 'Comentario',
      'aria-label': `Comentario ${a.full_name}`,
    });
    return { a, present, comment };
  });

  const save = async () => {
    try {
      await put(`/practices/${p.id}/attendance`, {
        items: rows.map((r) => ({ athlete_id: r.a.athlete_id, present: r.present.checked, comment: r.comment.value || null })),
      });
      toast('Lista guardada.');
    } catch (error) {
      toastError(error);
    }
  };

  const presentCount = () => rows.filter((r) => r.present.checked).length;
  const counter = h('span.muted', null, '');
  const refreshCounter = () => (counter.textContent = `${presentCount()} de ${rows.length} presentes`);
  rows.forEach((r) => r.present.addEventListener('change', refreshCounter));
  refreshCounter();

  return card(
    'Pasar lista',
    table(
      [
        { label: 'Presente', render: (r) => r.present, class: 'center' },
        { label: 'Alumno', render: (r) => r.a.full_name },
        { label: 'Confirmación', render: (r) => badge(LABELS.rsvp[r.a.rsvp], RSVP_VARIANT[r.a.rsvp]) },
        { label: 'Comentario', render: (r) => r.comment },
      ],
      rows,
      { empty: 'El grupo no tiene alumnos activos.', testId: 'attendance-table' },
    ),
    rows.length
      ? h(
          'div.actions.between',
          null,
          counter,
          h('button.btn.primary', { type: 'button', 'data-testid': 'save-attendance', onClick: save }, 'Guardar lista'),
        )
      : null,
  );
}

function measurementsCard(p) {
  const list = h('div');
  const render = (items) =>
    mount(
      list,
      table(
        [
          { label: 'Alumno', render: (m) => m.athlete_name },
          { label: 'Prueba / métrica', render: (m) => m.metric },
          { label: 'Marca', render: (m) => `${Number(m.value)} ${m.unit}`, class: 'num' },
          { label: 'Nota', render: (m) => m.notes ?? '' },
        ],
        items,
        { empty: 'Sin marcas registradas.', testId: 'measurements-table' },
      ),
    );
  render(p.measurements);

  const form = h(
    'form.form-grid.compact-grid',
    { 'data-testid': 'measurement-form' },
    field({
      label: 'Alumno',
      name: 'athlete_id',
      type: 'select',
      required: true,
      options: p.attendance.map((a) => ({ value: a.athlete_id, label: a.full_name })),
    }),
    field({ label: 'Prueba / métrica', name: 'metric', required: true, placeholder: '50 m libre' }),
    field({ label: 'Valor', name: 'value', type: 'number', step: '0.01', required: true }),
    field({
      label: 'Unidad',
      name: 'unit',
      type: 'select',
      required: true,
      options: ['s', 'min', 'm', 'kg', 'reps', 'pts'].map((u) => ({ value: u, label: u })),
    }),
    field({ label: 'Nota', name: 'notes' }),
    h('div.actions', null, h('button.btn.primary', { type: 'submit' }, 'Registrar marca')),
  );
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      await post(`/practices/${p.id}/measurements`, readForm(form));
      toast('Marca registrada.');
      form.reset();
      render((await get(`/practices/${p.id}`)).measurements);
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) showErrors(form, error.errors);
      else toastError(error);
    }
  });

  return card('Marcas y estadísticas', form, list);
}

function incidentsCard(p) {
  const list = h('div');
  const render = (items) =>
    mount(
      list,
      table(
        [
          { label: 'Tipo', render: (i) => LABELS.incidentType[i.type] },
          {
            label: 'Gravedad',
            render: (i) =>
              badge(LABELS.severity[i.severity], i.severity === 'high' ? 'danger' : i.severity === 'medium' ? 'warning' : 'neutral'),
          },
          { label: 'Alumno', render: (i) => i.athlete_name ?? 'General' },
          { label: 'Descripción', render: (i) => i.description },
          { label: 'Fecha', render: (i) => formatDate(i.created_at) },
        ],
        items,
        { empty: 'Sin incidencias. ¡Buena práctica!', testId: 'incidents-table' },
      ),
    );
  render(p.incidents);

  const form = h(
    'form.form-grid.compact-grid',
    { 'data-testid': 'incident-form' },
    field({
      label: 'Tipo',
      name: 'type',
      type: 'select',
      required: true,
      options: Object.entries(LABELS.incidentType).map(([value, label]) => ({ value, label })),
    }),
    field({
      label: 'Gravedad',
      name: 'severity',
      type: 'select',
      required: true,
      options: Object.entries(LABELS.severity).map(([value, label]) => ({ value, label })),
    }),
    field({
      label: 'Alumno (opcional)',
      name: 'athlete_id',
      type: 'select',
      options: p.attendance.map((a) => ({ value: a.athlete_id, label: a.full_name })),
      placeholder: 'General / grupo',
    }),
    field({ label: 'Descripción', name: 'description', type: 'textarea', required: true }),
    h('div.actions', null, h('button.btn.primary', { type: 'submit' }, 'Registrar incidencia')),
  );
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      await post(`/practices/${p.id}/incidents`, readForm(form));
      toast('Incidencia registrada.');
      form.reset();
      render((await get(`/practices/${p.id}`)).incidents);
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) showErrors(form, error.errors);
      else toastError(error);
    }
  });

  return card('Lesiones e incidencias', form, list);
}
