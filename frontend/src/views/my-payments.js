import { h, badge, table } from '../ui/dom.js';
import { card, kpi, pageHeader } from '../ui/layout.js';
import { LABELS, formatCLP, formatDate, formatPeriod } from '../ui/format.js';
import { get } from '../api/client.js';

/** Pagos y atrasos del apoderado (de sus pupilos) o del alumno. */
export async function myPaymentsView() {
  const charges = await get('/me/charges');
  const pending = charges.filter((c) => c.status !== 'paid');
  const overdue = pending.filter((c) => c.is_overdue);

  return h(
    'div',
    null,
    pageHeader('Mis pagos', 'Estado de mensualidades y pagos registrados por el club.'),
    h(
      'div.kpis',
      null,
      kpi(
        'Saldo pendiente',
        formatCLP(pending.reduce((s, c) => s + c.balance, 0)),
        pending.length ? 'warning' : 'success',
        'my-balance',
        'wallet',
      ),
      kpi('Mensualidades atrasadas', overdue.length, overdue.length ? 'danger' : 'success', 'my-overdue', 'alert'),
      kpi(
        'Pagos realizados',
        charges.reduce((s, c) => s + c.payments.length, 0),
      ),
    ),
    card(
      'Historial',
      table(
        [
          { label: 'Alumno', render: (c) => c.athlete_name },
          { label: 'Mes', render: (c) => formatPeriod(c.period) },
          { label: 'Vencimiento', render: (c) => formatDate(c.due_date) },
          { label: 'Monto', render: (c) => formatCLP(c.amount), class: 'num' },
          { label: 'Saldo', render: (c) => formatCLP(c.balance), class: 'num' },
          {
            label: 'Estado',
            render: (c) =>
              c.status === 'paid'
                ? badge('Pagado', 'success')
                : c.is_overdue
                  ? badge(`Atrasado ${c.days_late} días`, 'danger')
                  : badge('Por vencer', 'warning'),
          },
          {
            label: 'Pagos',
            render: (c) =>
              c.payments.length
                ? h(
                    'ul.plain.small',
                    null,
                    c.payments.map((p) => h('li', null, `${formatDate(p.paid_at)} · ${formatCLP(p.amount)} · ${LABELS.method[p.method]}`)),
                  )
                : '—',
          },
        ],
        charges,
        { empty: 'No hay mensualidades registradas.', testId: 'my-charges' },
      ),
    ),
  );
}
