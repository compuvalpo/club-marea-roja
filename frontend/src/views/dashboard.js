import { h, emptyState, table, badge } from '../ui/dom.js';
import { card, kpi, pageHeader } from '../ui/layout.js';
import { formatCLP, formatPeriod, formatDate } from '../ui/format.js';
import { get } from '../api/client.js';
import { can } from '../api/session.js';
import { icon } from '../ui/icons.js';
import { practiceItem } from './shared.js';

export async function dashboardView({ user }) {
  const data = await get('/dashboard');
  const firstName = user.name.split(' ')[0];
  const upcoming = card(
    'Próximas prácticas (7 días)',
    data.upcoming_practices.length
      ? h(
          'ul.practice-list',
          { 'data-testid': 'upcoming' },
          data.upcoming_practices.map((p) =>
            practiceItem(p, { showRsvp: ['guardian', 'athlete'].includes(user.role) && can('practices.rsvp', user) }),
          ),
        )
      : emptyState('No hay prácticas programadas esta semana.'),
  );

  const blocks = [pageHeader(`Hola, ${firstName}`, 'Resumen de su actividad en el club')];

  if (user.role === 'admin') {
    const k = data.kpis;
    blocks.push(
      h(
        'div.kpis',
        null,
        kpi('Alumnos activos', k.active_athletes, '', 'kpi-athletes', 'swim'),
        kpi('Ingresos del mes', formatCLP(k.month_income), 'success', undefined, 'up'),
        kpi('Egresos del mes', formatCLP(k.month_expense), 'danger', undefined, 'down'),
        kpi('Balance del mes', formatCLP(k.month_balance), k.month_balance >= 0 ? 'success' : 'danger', undefined, 'balance'),
        kpi('Mensualidades vencidas', k.delinquents, k.delinquents ? 'warning' : '', 'kpi-delinquents', 'alert'),
        kpi('Pendientes este mes', k.pending_this_month, '', undefined, 'receipt'),
      ),
      h(
        'div.grid-2',
        null,
        upcoming,
        card(
          'Accesos rápidos',
          h(
            'div.quick',
            null,
            [
              ['#/finance?tab=delinquents', 'alert', 'Ver morosos'],
              ['#/finance?tab=charges', 'card', 'Registrar pago'],
              ['#/calendar', 'calendar-plus', 'Asignar práctica'],
              ['#/events', 'trophy', 'Crear evento'],
            ].map(([href, ic, label]) => h('a.btn.ghost', { href }, icon(ic, { size: 16 }), label)),
          ),
        ),
      ),
    );
  } else if (user.role === 'coach') {
    blocks.push(upcoming);
  } else {
    blocks.push(
      h(
        'div.kpis',
        null,
        kpi(
          user.role === 'guardian' ? 'Pupilos' : 'Mi ficha',
          data.athletes.length,
          '',
          undefined,
          user.role === 'guardian' ? 'team' : 'user',
        ),
        kpi('Deuda pendiente', formatCLP(data.pending_total), data.pending_total ? 'warning' : 'success', 'kpi-debt', 'wallet'),
      ),
      h(
        'div.grid-2',
        null,
        upcoming,
        card(
          'Pagos pendientes',
          table(
            [
              { label: 'Alumno', render: (c) => c.athlete_name },
              { label: 'Mes', render: (c) => formatPeriod(c.period) },
              { label: 'Vence', render: (c) => formatDate(c.due_date) },
              { label: 'Saldo', render: (c) => formatCLP(c.balance), class: 'num' },
              {
                label: 'Estado',
                render: (c) => (c.is_overdue ? badge(`Atrasado ${c.days_late} días`, 'danger') : badge('Por vencer', 'warning')),
              },
            ],
            data.pending_charges,
            { empty: '¡Está al día! No tiene pagos pendientes.' },
          ),
        ),
      ),
      card(
        user.role === 'guardian' ? 'Mis pupilos' : 'Mi ficha',
        h(
          'div.chips',
          null,
          data.athletes.map((a) =>
            h(
              'a.chip',
              { href: `#/athletes/${a.id}/stats` },
              icon('chart', { size: 16 }),
              `${a.full_name} · ${a.group_name ?? 'Sin grupo'}`,
            ),
          ),
        ),
      ),
    );
  }

  return h('div', null, blocks);
}
