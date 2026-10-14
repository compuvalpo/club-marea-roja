import { Calendar } from '@fullcalendar/core';
import esLocale from '@fullcalendar/core/locales/es';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import { h, toast, toastError } from '../ui/dom.js';
import { field, options } from '../ui/form.js';
import { formModal, openModal } from '../ui/modal.js';
import { pageHeader } from '../ui/layout.js';
import { LABELS, formatDateTime, isoDate } from '../ui/format.js';
import { get, patch, post } from '../api/client.js';
import { can } from '../api/session.js';
import { navigate } from '../router.js';
import { rsvpControls } from './shared.js';
import { eventDetail } from './events.js';
import { icon } from '../ui/icons.js';

const RSVP_ICON = { confirmed: '✅', cancelled: '❌', pending: '⏳' };

/**
 * Calendario unificado de prácticas (regulares) y eventos (ocasionales).
 * Admin: crea prácticas haciendo clic en un día y las mueve arrastrándolas.
 */
export async function calendarView({ user }) {
  const canPlan = can('practices.plan', user); // crear, mover y eliminar prácticas
  const isFamily = ['guardian', 'athlete'].includes(user.role);
  const [groups, venues, coaches] = await Promise.all([
    get('/groups'),
    get('/venues'),
    can('users.manage', user) ? get('/users', { role: 'coach' }) : Promise.resolve([]),
  ]);
  let groupFilter = '';

  const container = h('div.calendar', { 'data-testid': 'calendar' });

  const toFcEvents = (practices, events) => [
    ...practices.map((p) => {
      const mine = p.my_athletes ?? [];
      const prefix = isFamily && mine.length ? mine.map((a) => RSVP_ICON[a.rsvp]).join('') + ' ' : '';
      return {
        id: `p-${p.id}`,
        title: `${prefix}${p.group_name}${p.status === 'cancelled' ? ' (suspendida)' : ''}`,
        start: `${p.date}T${p.start_time}`,
        end: `${p.date}T${p.end_time}`,
        backgroundColor: p.status === 'cancelled' ? '#94a3b8' : p.group_color,
        borderColor: p.group_color,
        editable: canPlan,
        extendedProps: { kind: 'practice', data: p },
      };
    }),
    ...events.map((e) => ({
      id: `e-${e.id}`,
      title: `🏆 ${e.title}`,
      start: e.start_at.replace(' ', 'T'),
      end: e.end_at.replace(' ', 'T'),
      backgroundColor: '#334155',
      borderColor: '#1e293b',
      editable: false,
      extendedProps: { kind: 'event', data: e },
    })),
  ];

  const calendar = new Calendar(container, {
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    locale: esLocale,
    timeZone: 'local',
    initialView: window.matchMedia('(max-width: 720px)').matches ? 'listWeek' : 'dayGridMonth',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
    height: 'auto',
    firstDay: 1,
    slotMinTime: '07:00:00',
    slotMaxTime: '22:30:00',
    nowIndicator: true,
    eventDisplay: 'block',
    dayMaxEvents: 3,
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    selectable: canPlan,
    editable: canPlan,
    events: async (info, success, failure) => {
      try {
        const from = isoDate(info.start);
        const end = new Date(info.end);
        end.setDate(end.getDate() - 1);
        const to = isoDate(end);
        const [practices, events] = await Promise.all([
          get('/practices', { from, to, group_id: groupFilter }),
          get('/events', { from, to }),
        ]);
        success(toFcEvents(practices, events));
      } catch (error) {
        toastError(error);
        failure(error);
      }
    },
    dateClick: canPlan ? (info) => createPractice(info.dateStr.slice(0, 10)) : undefined,
    eventClick: (info) => {
      info.jsEvent.preventDefault();
      const { kind, data } = info.event.extendedProps;
      if (kind === 'event') eventDetail(data.id, user, () => calendar.refetchEvents());
      else if (isFamily) practiceModal(data);
      else navigate(`/practices/${data.id}`);
    },
    eventDrop: async (info) => {
      const { data } = info.event.extendedProps;
      const start = info.event.start;
      const pad = (n) => String(n).padStart(2, '0');
      const durationMs = new Date(`${data.date}T${data.end_time}`) - new Date(`${data.date}T${data.start_time}`);
      const end = new Date(start.getTime() + durationMs);
      try {
        await patch(`/practices/${data.id}`, {
          date: isoDate(start),
          start_time: `${pad(start.getHours())}:${pad(start.getMinutes())}`,
          end_time: `${pad(end.getHours())}:${pad(end.getMinutes())}`,
        });
        toast('Práctica reprogramada.');
        calendar.refetchEvents();
      } catch (error) {
        toastError(error);
        info.revert();
      }
    },
  });

  function practiceModal(p) {
    openModal(
      `${p.group_name} · ${p.start_time}–${p.end_time}`,
      h(
        'div.stack',
        null,
        h('p', null, `${formatDateTime(`${p.date} ${p.start_time}`)} · ${p.venue_name ?? 'Recinto por definir'}`),
        p.objectives ? h('p', null, h('strong', null, 'Objetivos: '), p.objectives) : null,
        p.status === 'scheduled'
          ? h(
              'div.rsvp-list',
              null,
              p.my_athletes.map((a) => rsvpControls(p.id, a, () => calendar.refetchEvents())),
            )
          : h('p.muted', null, `Estado: ${LABELS.practiceStatus[p.status]}`),
        h('a.btn.ghost', { href: `#/practices/${p.id}` }, 'Ver detalle'),
      ),
    );
  }

  function createPractice(date) {
    formModal(
      'Asignar práctica',
      [
        field({ label: 'Grupo', name: 'group_id', type: 'select', required: true, options: options(groups) }),
        field({ label: 'Entrenador', name: 'coach_id', type: 'select', options: options(coaches) }),
        field({ label: 'Recinto', name: 'venue_id', type: 'select', options: options(venues) }),
        field({ label: 'Fecha', name: 'date', type: 'date', required: true, value: date }),
        field({ label: 'Inicio', name: 'start_time', type: 'time', required: true, value: '17:00' }),
        field({ label: 'Término', name: 'end_time', type: 'time', required: true, value: '18:30' }),
        field({ label: 'Objetivos', name: 'objectives', type: 'textarea' }),
      ],
      async (data) => {
        if (!data.coach_id) data.coach_id = groups.find((g) => g.id === data.group_id)?.coach_id ?? null;
        await post('/practices', data);
        toast('Práctica asignada.');
        calendar.refetchEvents();
      },
    );
  }

  const filter = h(
    'select.inline-select',
    { 'aria-label': 'Filtrar por grupo', onChange: (e) => ((groupFilter = e.target.value), calendar.refetchEvents()) },
    h('option', { value: '' }, 'Todos los grupos'),
    groups.map((g) => h('option', { value: g.id }, g.name)),
  );

  const legend = h(
    'div.legend',
    null,
    groups.map((g) => h('span', null, h('i', { style: { background: g.color } }), g.name)),
    h('span', null, h('i', { style: { background: '#334155' } }), 'Eventos'),
    isFamily ? h('span.muted', null, '✅ confirmado · ❌ cancelado · ⏳ sin confirmar') : null,
  );

  // Se renderiza cuando el contenedor ya está montado en el DOM (FullCalendar necesita medirlo).
  setTimeout(() => calendar.render(), 0);

  return h(
    'div',
    null,
    pageHeader(
      'Calendario',
      canPlan ? 'Haga clic en un día para asignar una práctica; arrástrela para reprogramarla.' : 'Prácticas y eventos del club',
      canPlan ? filter : null,
      canPlan
        ? h(
            'button.btn.primary',
            { type: 'button', 'data-testid': 'new-practice', onClick: () => createPractice(isoDate()) },
            icon('plus', { size: 16 }),
            'Nueva práctica',
          )
        : null,
    ),
    legend,
    h('section.card', null, container),
  );
}
