import { h, badge, emptyState, mount, table, toast, toastError } from '../ui/dom.js';
import { field, options } from '../ui/form.js';
import { confirmDialog, formModal, openModal } from '../ui/modal.js';
import { card, pageHeader } from '../ui/layout.js';
import { LABELS, RSVP_VARIANT, formatDateTime, isoDate } from '../ui/format.js';
import { del, get, patch, post } from '../api/client.js';
import { can } from '../api/session.js';
import { icon } from '../ui/icons.js';

const toInput = (value) => (value ? value.slice(0, 16).replace(' ', 'T') : '');
const fromInput = (value) => (value ? value.replace('T', ' ').slice(0, 16) : null);

/**
 * Formulario de evento: el lugar puede ser un recinto registrado o una ubicación libre.
 */
function eventFields(venues, e = {}) {
  const useVenue = e.id ? e.venue_id !== null : true;
  const venueField = field({ label: 'Recinto registrado', name: 'venue_id', type: 'select', value: e.venue_id, options: options(venues) });
  const textField = field({
    label: 'Otra ubicación (texto libre)',
    name: 'location_text',
    value: e.location_text,
    placeholder: 'Ej.: Parque Alejo Barrios, Valparaíso',
  });
  const toggle = (venue) => {
    venueField.hidden = !venue;
    textField.hidden = venue;
    if (venue) textField.querySelector('input').value = '';
    else venueField.querySelector('select').value = '';
  };
  const radios = h(
    'fieldset.field.inline-radios',
    null,
    h('legend', null, 'Lugar del evento'),
    h(
      'label',
      null,
      h('input', { type: 'radio', name: '_place', value: 'venue', checked: useVenue, onChange: () => toggle(true) }),
      ' Recinto registrado',
    ),
    h(
      'label',
      null,
      h('input', { type: 'radio', name: '_place', value: 'text', checked: !useVenue, onChange: () => toggle(false) }),
      ' Otro lugar',
    ),
  );
  toggle(useVenue);

  return [
    field({ label: 'Título', name: 'title', required: true, value: e.title }),
    field({
      label: 'Tipo',
      name: 'type',
      type: 'select',
      required: true,
      value: e.type ?? 'competition',
      options: Object.entries(LABELS.eventType).map(([value, label]) => ({ value, label })),
    }),
    field({
      label: 'Inicio',
      name: 'start_at',
      type: 'datetime-local',
      required: true,
      value: toInput(e.start_at) || `${isoDate()}T09:00`,
    }),
    field({ label: 'Término', name: 'end_at', type: 'datetime-local', required: true, value: toInput(e.end_at) || `${isoDate()}T13:00` }),
    radios,
    venueField,
    textField,
    field({ label: 'Descripción', name: 'description', type: 'textarea', value: e.description }),
  ];
}

const normalize = (data) => {
  const { _place: _ignored, ...rest } = data;
  return { ...rest, start_at: fromInput(rest.start_at), end_at: fromInput(rest.end_at) };
};

/**
 * Modal de detalle de evento (usado también desde el calendario).
 */
export async function eventDetail(id, user, onChange) {
  try {
    const e = await get(`/events/${id}`);
    const isFamily = ['guardian', 'athlete'].includes(user.role) && can('events.rsvp', user);
    const rsvp = async (athleteId, status) => {
      try {
        await post(`/events/${id}/rsvp`, { athlete_id: athleteId, status });
        toast(status === 'confirmed' ? 'Participación confirmada.' : 'Participación cancelada.');
        modal.close();
        onChange?.();
      } catch (error) {
        toastError(error);
      }
    };
    const modal = openModal(
      e.title,
      h(
        'div.stack',
        null,
        h('p', null, badge(LABELS.eventType[e.type], 'info'), ' ', `${formatDateTime(e.start_at)} – ${formatDateTime(e.end_at)}`),
        h(
          'p.with-icon',
          null,
          icon('pin', { size: 16 }),
          e.location ?? '—',
          e.venue_address ? h('small.muted', null, ` · ${e.venue_address}`) : null,
        ),
        e.description ? h('p', null, e.description) : null,
        h('h3', null, isFamily ? 'Convocatoria' : 'Convocados'),
        e.participants.length
          ? h(
              'ul.plain',
              null,
              e.participants.map((p) =>
                h(
                  'li.rsvp',
                  null,
                  h('span.rsvp-name', null, p.full_name),
                  badge(LABELS.rsvp[p.rsvp], RSVP_VARIANT[p.rsvp]),
                  isFamily
                    ? [
                        h('button.btn.sm.success', { type: 'button', onClick: () => rsvp(p.athlete_id, 'confirmed') }, 'Confirmar'),
                        h('button.btn.sm.danger-outline', { type: 'button', onClick: () => rsvp(p.athlete_id, 'cancelled') }, 'Cancelar'),
                      ]
                    : null,
                ),
              ),
            )
          : emptyState(isFamily ? 'Sus pupilos no están convocados a este evento.' : 'Aún no hay convocados.'),
      ),
    );
  } catch (error) {
    toastError(error);
  }
}

export async function eventsView({ user }) {
  const canManage = can('events.manage', user);
  const [venues, athletes] = await Promise.all([get('/venues'), canManage ? get('/athletes') : []]);
  let events = await get('/events');
  let showPast = false;
  const box = h('div');

  const reload = async () => {
    events = await get('/events');
    render();
  };

  const summon = (e) => {
    const checks = athletes
      .filter((a) => a.active)
      .map((a) => h('label.check', null, h('input', { type: 'checkbox', value: a.id }), `${a.full_name} · ${a.group_name ?? ''}`));
    const modal = openModal(
      `Convocar a ${e.title}`,
      h(
        'div.stack',
        null,
        h('div.check-grid', null, checks),
        h(
          'div.actions',
          null,
          h(
            'button.btn.primary',
            {
              type: 'button',
              onClick: async () => {
                const ids = checks
                  .map((c) => c.querySelector('input'))
                  .filter((i) => i.checked)
                  .map((i) => Number(i.value));
                if (!ids.length) return toast('Seleccione al menos un alumno.', 'error');
                try {
                  await post(`/events/${e.id}/participants`, { athlete_ids: ids });
                  toast(`${ids.length} alumnos convocados.`);
                  modal.close();
                } catch (error) {
                  toastError(error);
                }
              },
            },
            'Convocar',
          ),
        ),
      ),
      { wide: true },
    );
  };

  function render() {
    const now = new Date().toISOString().slice(0, 10);
    const rows = events.filter((e) => (showPast ? e.end_at.slice(0, 10) < now : e.end_at.slice(0, 10) >= now));
    if (showPast) rows.reverse();
    mount(
      box,
      table(
        [
          { label: 'Evento', render: (e) => h('button.link', { type: 'button', onClick: () => eventDetail(e.id, user, reload) }, e.title) },
          { label: 'Tipo', render: (e) => LABELS.eventType[e.type] },
          { label: 'Fecha', render: (e) => formatDateTime(e.start_at) },
          { label: 'Lugar', render: (e) => e.location ?? '—' },
          canManage
            ? {
                label: '',
                class: 'actions-cell',
                render: (e) =>
                  h(
                    'div.row-actions',
                    null,
                    h('button.btn.sm.primary', { type: 'button', onClick: () => summon(e) }, 'Convocar'),
                    h(
                      'button.btn.sm.ghost',
                      {
                        type: 'button',
                        onClick: () =>
                          formModal(
                            'Editar evento',
                            eventFields(venues, e),
                            async (d) => (await patch(`/events/${e.id}`, normalize(d)), toast('Evento actualizado.'), reload()),
                          ),
                      },
                      'Editar',
                    ),
                    h(
                      'button.btn.sm.danger-outline',
                      {
                        type: 'button',
                        onClick: async () => {
                          if (!(await confirmDialog(`¿Eliminar ${e.title}?`, { danger: true, confirmLabel: 'Eliminar' }))) return;
                          await del(`/events/${e.id}`).catch(toastError);
                          await reload();
                        },
                      },
                      'Eliminar',
                    ),
                  ),
              }
            : null,
        ].filter(Boolean),
        rows,
        { empty: showPast ? 'No hay eventos pasados.' : 'No hay eventos próximos.', testId: 'events-table' },
      ),
    );
  }

  render();

  return h(
    'div',
    null,
    pageHeader(
      'Eventos',
      'Competencias, reuniones y actividades ocasionales, en el club o en otros recintos.',
      h('label.check', null, h('input', { type: 'checkbox', onChange: (e) => ((showPast = e.target.checked), render()) }), 'Ver pasados'),
      canManage
        ? h(
            'button.btn.primary',
            {
              type: 'button',
              'data-testid': 'new-event',
              onClick: () =>
                formModal(
                  'Nuevo evento',
                  eventFields(venues),
                  async (d) => (await post('/events', normalize(d)), toast('Evento creado.'), reload()),
                  { wide: true },
                ),
            },
            icon('plus', { size: 16 }),
            'Nuevo evento',
          )
        : null,
    ),
    card(null, box),
  );
}
