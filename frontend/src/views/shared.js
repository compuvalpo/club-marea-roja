import { h, badge, toast, toastError } from '../ui/dom.js';
import { LABELS, RSVP_VARIANT, formatWeekday } from '../ui/format.js';
import { post } from '../api/client.js';

/**
 * Botones Confirmar / Cancelar asistencia de un alumno a una práctica.
 * @param {{athlete_id: number, full_name: string, rsvp: string}} entry
 */
export function rsvpControls(practiceId, entry, onChange) {
  const state = badge(LABELS.rsvp[entry.rsvp], RSVP_VARIANT[entry.rsvp]);
  const send = async (status) => {
    try {
      await post(`/practices/${practiceId}/rsvp`, { athlete_id: entry.athlete_id, status });
      entry.rsvp = status;
      state.textContent = LABELS.rsvp[status];
      state.className = `badge ${RSVP_VARIANT[status]}`;
      toast(status === 'confirmed' ? `Asistencia confirmada para ${entry.full_name}.` : `Asistencia cancelada para ${entry.full_name}.`);
      onChange?.(status);
    } catch (error) {
      toastError(error);
    }
  };

  return h(
    'div.rsvp',
    { dataset: { testid: `rsvp-${practiceId}-${entry.athlete_id}` } },
    h('span.rsvp-name', null, entry.full_name),
    state,
    h('button.btn.sm.success', { type: 'button', onClick: () => send('confirmed') }, 'Confirmar'),
    h('button.btn.sm.danger-outline', { type: 'button', onClick: () => send('cancelled') }, 'Cancelar'),
  );
}

/** Ítem de lista de práctica. */
export function practiceItem(p, { showRsvp = false } = {}) {
  return h(
    'li.practice-item',
    { style: { borderLeftColor: p.group_color ?? '#be123c' } },
    h(
      'a',
      { href: `#/practices/${p.id}` },
      h('strong', null, `${p.group_name} · ${p.start_time}–${p.end_time}`),
      h('small', null, `${formatWeekday(p.date)} · ${p.venue_name ?? 'Recinto por definir'}`),
      p.status !== 'scheduled' ? badge(LABELS.practiceStatus[p.status], p.status === 'cancelled' ? 'danger' : 'neutral') : null,
    ),
    showRsvp && p.my_athletes?.length
      ? h(
          'div.rsvp-list',
          null,
          p.my_athletes.map((a) => rsvpControls(p.id, a)),
        )
      : null,
  );
}
